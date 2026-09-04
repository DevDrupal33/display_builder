<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\SourceWithChoicesInterface;

/**
 * Helper class for component library definition handling.
 */
class ComponentLibraryDefinitionHelper {

  /**
   * The UI Patterns source plugin manager.
   */
  protected SourcePluginManager $sourceManager;

  /**
   * The UI Patterns source plugin manager.
   */
  private ComponentPluginManager $sdcManager;

  public function __construct(ComponentPluginManager $sdcManager, SourcePluginManager $sourceManager) {
    $this->sdcManager = $sdcManager;
    $this->sourceManager = $sourceManager;
  }

  /**
   * Init filtered and grouped definitions, and source data.
   *
   * @param array $configuration
   *   Component library panel definition.
   *
   * @return array
   *   An associative array of arrays, where each sub array is keyed by
   *    component ID.
   */
  public function getDefinitions(array $configuration): array {
    /** @var \Drupal\ui_patterns\ComponentPluginManager $uiPatternsSdcManager */
    $uiPatternsSdcManager = $this->sdcManager;
    $definitions = $uiPatternsSdcManager->getNegotiatedSortedDefinitions();
    $filtered = $grouped = $sources = [];
    $exclude_by_id = \preg_split('/\r\n|\r|\n|\s+/', \trim($configuration['exclude_id'] ?? '')) ?: [];
    /** @var \Drupal\ui_patterns\SourceWithChoicesInterface $source */
    $source = $this->sourceManager->createInstance('component');

    foreach ($definitions as $id => $definition) {
      if (!self::filterAccordingToConfiguration($id, $definition, $configuration, $exclude_by_id)) {
        continue;
      }

      $component = $this->sdcManager->find($id);
      $data = $this->prepareComponentData($source, $component);

      if ($data === NULL) {
        // We skip components with at least a required prop without default
        // value in order to avoid \Twig\Error\RuntimeError.
        // In UI Patterns 2, we rely on Form API blocking ComponentForm
        // submission to avoid this unfortunate situation.
        // In Display Builder, we can render component without submitting
        // ComponentForm, on preview and builder panels for example.
        // @todo Do we send a log message?
        continue;
      }

      $sources[$id] = $data;
      $filtered[$id] = $definition;
      $grouped[(string) $definition['category']][$id] = $definition;
    }

    // Order list ignoring starting '(' that is used for components names that
    // are sub components.
    \uasort($filtered, static function ($a, $b) {
      $nameA = \ltrim($a['name'] ?? $a['label'], '(');

      return \strnatcasecmp($nameA, \ltrim($b['name'] ?? $b['label'], '('));
    });

    return [
      'filtered' => $filtered,
      'grouped' => $grouped,
      'sources' => $sources,
    ];
  }

  /**
   * Prepare component data.
   *
   * @param \Drupal\ui_patterns\SourceWithChoicesInterface $source
   *   Component source.
   * @param \Drupal\Core\Plugin\Component $component
   *   Component.
   *
   * @return ?array
   *   The component data, with the default value for required props.
   *   If NULL, that means a required prop has no default value and the
   *   component will be skipped.
   */
  private function prepareComponentData(SourceWithChoicesInterface $source, Component $component): ?array {
    $data = $source->getChoiceSettings($component->getPluginId());
    $required = $component->metadata->schema['required'] ?? NULL;

    if (!$required) {
      return $data;
    }

    $props = $component->metadata->schema['properties'] ?? [];
    $source_cache = [];

    foreach ($required as $prop_id) {
      $prop = $props[$prop_id];

      $default = self::getDefaultValue($prop);

      if ($default === NULL) {
        return NULL;
      }
      $prop_type = $prop['ui_patterns']['type_definition'] ?? NULL;

      if (!$prop_type) {
        return NULL;
      }
      $prop_source_id = $prop_type->getDefaultSourceId();

      if (!$prop_source_id) {
        return NULL;
      }

      if (!isset($source_cache[$prop_source_id])) {
        /** @var \Drupal\ui_patterns\SourceInterface $default_source */
        $source_cache[$prop_source_id] = $this->sourceManager->createInstance($prop_source_id);
      }
      $default_source = $source_cache[$prop_source_id];

      // We bet on widgets having a 'value' configuration. It may be a bit
      // naive, but the most important is to avoid \Twig\Error\RuntimeError.
      $definition = $default_source->getPluginDefinition();
      $tags = ($definition instanceof PluginDefinitionInterface) ? [] : $definition['tags'] ?? [];

      if (!\in_array('widget', $tags, TRUE)) {
        return NULL;
      }

      $data['component']['props'][$prop_id] = [
        'source_id' => $default_source->getPluginId(),
        'source' => [
          'value' => $default,
        ],
      ];
    }

    return $data;
  }

  /**
   * Filter definitions according to configuration.
   *
   * @param string $id
   *   Component ID, the array key.
   * @param array $definition
   *   Component definition, the array value.
   * @param array $configuration
   *   Component library panel configuration.
   * @param string[] $exclude_by_id
   *   An array of component IDs to exclude.
   *
   * @return bool
   *   Must the component be kept?
   */
  private static function filterAccordingToConfiguration(string $id, array $definition, array $configuration, array $exclude_by_id): bool {
    if (isset($definition['provider']) && \in_array($id, $exclude_by_id, TRUE)) {
      return FALSE;
    }

    if (isset($definition['provider']) && \in_array($definition['provider'], $configuration['exclude'], TRUE)) {
      return FALSE;
    }

    // Excluded no ui components unless forced.
    if (isset($definition['noUi']) && $definition['noUi'] === TRUE) {
      if ((bool) $configuration['include_no_ui'] !== TRUE) {
        return FALSE;
      }
    }

    // Filter components according to configuration.
    // Components with stable or undefined status will always be available.
    $allowed_status = \array_merge($configuration['component_status'], ['stable']);

    if (isset($definition['status']) && !\in_array($definition['status'], $allowed_status, TRUE)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get default value for prop.
   *
   * @param array $prop
   *   Prop JSON schema definition.
   *
   * @return mixed
   *   NULL if no default value found.
   */
  private static function getDefaultValue(array $prop): mixed {
    // First, we try to get the default value or the first example.
    $default = $prop['default'] ?? $prop['examples'][0] ?? NULL;

    if ($default !== NULL) {
      return $default;
    }

    // Then, we try to get the first value from enumeration.
    if (isset($prop['enum']) && !empty($prop['enum'])) {
      return $prop['enum'][0];
    }

    // Finally, we set the boolean value to false. Boolean is the only JSON
    // schema type where we can be sure the default value is OK because there is
    // no additional criteria to deal with:
    // - string has minLength, maxLength, pattern...
    // - array has items, minItems, maxItems, uniqueItems...
    // - object has properties...
    // - number and integer have multipleOf, minimum, maximum...
    // There is this weird mechanism in SDC adding the object type to all
    // props. We need to deal with that until we remove it.
    // @see \Drupal\Core\Theme\Component\ComponentMetadata::parseSchemaInfo()
    if ($prop['type'] === 'boolean' || empty(\array_diff($prop['type'], ['object', 'boolean']))) {
      return FALSE;
    }

    return NULL;
  }

}
