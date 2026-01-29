<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\ComponentSource as UpstreamComponentSource;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'component',
  label: new TranslatableMarkup('Component'),
  description: new TranslatableMarkup('Add a Component'),
  prop_types: ['slot']
)]
class ComponentSource extends UpstreamComponentSource implements SourceWithSlotsInterface {

  /**
   * {@inheritdoc}
   */
  public function getChoice(array $settings): string {
    return $settings['component']['component_id'] ?? $settings['component_id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotDefinitions(): array {
    $component_id = $this->settings['component']['component_id'] ?? '';

    if (!$component_id) {
      return [];
    }

    try {
      $definition = $this->componentManager->getDefinition($component_id);
    }
    catch (\Throwable $th) {
      return [];
    }

    return $definition['slots'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValues(): array {
    $slots = [];

    foreach ($this->settings['component']['slots'] ?? [] as $slot_id => $slot) {
      if (isset($slot['sources'])) {
        $slots[$slot_id] = $this->getSlotValue($slot_id);
      }
    }

    return $slots;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValue(string $slot_id): array {
    return $this->settings['component']['slots'][$slot_id]['sources'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setSlotValue(array $data, string $slot_id, array $slot): array {
    $data['component']['slots'][$slot_id]['sources'] = $slot;

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setSlotRenderable(array $build, string $slot_id, array $slot): array {
    $build['#slots'][$slot_id] = $slot;
    // Prevent the slot to be generated again.
    unset($build['#ui_patterns']['slots'][$slot_id]);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSlotPath(string $slot_id): array {
    return ['component', 'slots', $slot_id, 'sources'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $data = $this->getSetting('component');

    if (empty($data['props'])) {
      return [];
    }

    $componentId = $data['component_id'];

    try {
      $component = $this->componentManager->getDefinition($componentId);
    }
    catch (\Throwable $th) {
      return [];
    }

    if (!$component) {
      return [];
    }

    $items = [];
    $propertyConfigs = $component['props']['properties'] ?? [];

    foreach ($data['props'] as $sourceId => $sourceConfig) {
      $summaryItem = $this->processProperty(
        $sourceConfig,
        $propertyConfigs[$sourceId] ?? NULL,
        $sourceId
      );

      if ($summaryItem) {
        $items[] = $summaryItem;
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormPropsOnly(array $form, FormStateInterface $form_state): array {
    $form = $this->settingsForm($form, $form_state);
    $data = $this->getSetting('component');
    $component_id = $data['component_id'] ?? NULL;

    if (!$component_id) {
      return $form;
    }

    if (!isset($form['component']['component_id'])) {
      $form['component']['component_id'] = [
        '#type' => 'hidden',
        '#value' => $component_id,
      ];
    }
    $form['component']['#render_slots'] = FALSE;
    $form['component']['#component_id'] = $component_id;

    return \array_merge(
      ['info' => $this->getComponentMetadata($component_id)],
      $form
    );
  }

  /**
   * Get component metadata.
   *
   * @param string $component_id
   *   The component ID.
   *
   * @return array
   *   A renderable array.
   */
  protected function getComponentMetadata(string $component_id): array {
    $component = $this->componentManager->find($component_id);
    $build = [];

    if ($description = $component->metadata->description) {
      $description = [
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $description,
          '#attributes' => [
            'class' => ['description'],
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'sl-button',
          '#value' => new TranslatableMarkup('Hide description'),
          '#attributes' => [
            'size' => 'small',
            'variant' => 'default',
            'class' => ['db-description-toggle'],
          ],
        ],
      ];
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        'content' => $description,
      ];
    }

    return $build;
  }

  /**
   * Processes a property configuration to generate a summary string.
   *
   * @param array $sourceConfig
   *   The source configuration array.
   * @param array|null $propertyConfig
   *   The property configuration array or NULL if not available.
   * @param string $sourceId
   *   The source identifier.
   *
   * @return string|null
   *   The formatted summary string or NULL if no value is available.
   */
  private function processProperty(array $sourceConfig, ?array $propertyConfig, string $sourceId): ?string {
    if ($this->isUiStyleAttribute($sourceConfig)) {
      return $this->formatUiStyleSummary($sourceConfig, $propertyConfig);
    }

    return $this->processStandardProperty($sourceConfig, $propertyConfig, $sourceId);
  }

  /**
   * Checks if the source configuration is for UI style attributes.
   *
   * @param array $sourceConfig
   *   The source configuration array.
   *
   * @return bool
   *   TRUE if it's a UI style attribute configuration, FALSE otherwise.
   */
  private function isUiStyleAttribute(array $sourceConfig): bool {
    return ($sourceConfig['source_id'] ?? NULL) === 'ui_styles_attributes'
          && isset($sourceConfig['source']['styles']['selected']);
  }

  /**
   * Formats a UI style summary string.
   *
   * @param array $sourceConfig
   *   The source configuration array.
   * @param array|null $propertyConfig
   *   The property configuration array or NULL if not available.
   *
   * @return string|null
   *   The formatted style summary or NULL if no styles are selected.
   */
  private function formatUiStyleSummary(array $sourceConfig, ?array $propertyConfig): ?string {
    $selectedStyles = $sourceConfig['source']['styles']['selected'];

    if (empty($selectedStyles)) {
      return NULL;
    }

    $mainLabel = $propertyConfig['title'] ?? '';
    $firstStyle = \array_key_first($selectedStyles);

    return $firstStyle ? \sprintf('%s - %s', $mainLabel, $firstStyle) : NULL;
  }

  /**
   * Processes a standard property configuration to generate a summary string.
   *
   * @param array $sourceConfig
   *   The source configuration array.
   * @param array|null $propertyConfig
   *   The property configuration array or NULL if not available.
   * @param string $sourceId
   *   The source identifier.
   *
   * @return string|null
   *   The formatted summary string or NULL if no value is available.
   */
  private function processStandardProperty(array $sourceConfig, ?array $propertyConfig, string $sourceId): ?string {
    if (!isset($sourceConfig['source']['value'])) {
      return NULL;
    }

    $value = $sourceConfig['source']['value'];
    $processedValue = self::normalizeValue($value);

    if ($processedValue === NULL) {
      return NULL;
    }

    $label = $propertyConfig['title'] ?? $sourceId;

    return \sprintf('%s: %s', $label, $processedValue);
  }

  /**
   * Normalizes a value to a string representation.
   *
   * @param mixed $value
   *   The value to normalize (array or string).
   *
   * @return string|null
   *   The normalized string value or NULL if empty/invalid.
   */
  private static function normalizeValue($value): ?string {
    if (\is_array($value)) {
      $str = self::flattenArrayToString($value);

      return $str !== '' ? $str : NULL;
    }

    return \is_string($value) && $value !== '' ? $value : NULL;
  }

  /**
   * Utility to stringify a nested array.
   *
   * @param array $array
   *   The $array to normalize (array or string).
   *
   * @return string
   *   The flatten string.
   */
  private static function flattenArrayToString(array $array): string {
    $result = [];

    foreach ($array as $key => $value) {
      if (\is_array($value)) {
        if (\is_int($key)) {
          $result[] = self::flattenArrayToString($value);
        }
        else {
          $result[] = $key . ': {' . self::flattenArrayToString($value) . '}';
        }
      }
      else {
        if (\is_int($key)) {
          $result[] = (string) $value;
        }
        else {
          $result[] = "{$key}: {$value}";
        }
      }
    }

    return \implode(', ', $result);
  }

}
