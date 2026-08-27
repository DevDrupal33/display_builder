<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\ComponentPluginManager;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\ComponentSource as UpstreamComponentSource;
use Drupal\ui_patterns\PropTypePluginManager;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\UiPatternsNormalizerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   *
   * The source plugin manager is appended to the upstream signature, so an
   * upstream change is a merge conflict rather than a silent reorder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PropTypePluginManager $propTypeManager,
    ContextRepositoryInterface $contextRepository,
    RouteMatchInterface $routeMatch,
    SampleEntityGeneratorInterface $sampleEntityGenerator,
    ModuleHandlerInterface $moduleHandler,
    Token $token,
    UiPatternsNormalizerInterface $normalizer,
    ComponentElementBuilder $componentElementBuilder,
    ComponentPluginManager $componentManager,
    protected SourcePluginManager $sourceManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $propTypeManager, $contextRepository, $routeMatch, $sampleEntityGenerator, $moduleHandler, $token, $normalizer, $componentElementBuilder, $componentManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ui_patterns_prop_type'),
      $container->get('context.repository'),
      $container->get('current_route_match'),
      $container->get('ui_patterns.sample_entity_generator'),
      $container->get('module_handler'),
      $container->get('token'),
      $container->get('ui_patterns.normalizer'),
      $container->get('ui_patterns.component_element_builder'),
      $container->get('plugin.manager.sdc'),
      $container->get('plugin.manager.ui_patterns_source'),
    );
  }

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
  public function setSlotValue(string $slot_id, array $slot): array {
    $this->settings['component']['slots'][$slot_id]['sources'] = $slot;

    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotCardinality(string $slot_id): int {
    $component_id = $this->settings['component']['component_id'] ?? '';

    if (!$component_id) {
      return self::CARDINALITY_UNLIMITED;
    }

    try {
      $definition = $this->componentManager->getDefinition($component_id);
    }
    catch (\Throwable $th) {
      return self::CARDINALITY_UNLIMITED;
    }

    return $definition['slots'][$slot_id]['maxItems'] ?? self::CARDINALITY_UNLIMITED;
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

    if (empty($data) || !isset($data['component_id'])) {
      return [];
    }

    try {
      $component = $this->componentManager->getDefinition($data['component_id']);
    }
    catch (\Throwable $th) {
      return [];
    }

    return \array_merge(
      $this->buildVariantSummary($component, $data),
      $this->buildPropsSummary($component, $data),
    );
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
   * Get the group name for this source plugin.
   *
   * This method will be implemented in Drupal\ui_patterns\SourceInterface.
   *
   * @return string
   *   The name of the group.
   */
  public function getGroup(): string {
    $component_id = $this->settings['component']['component_id'] ?? '';

    if (!$component_id) {
      return '';
    }

    $definition = $this->componentManager->getDefinition($component_id);

    return $definition['group'] ?? '';
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
   * Builds variant summary items.
   *
   * @param array $component
   *   The component definition.
   * @param array $data
   *   The component data.
   *
   * @return array
   *   Summary items for variants.
   */
  private function buildVariantSummary(array $component, array $data): array {
    if (!isset($data['variant_id']['source']['value'])
        || $data['variant_id']['source']['value'] === 'default') {
      return [];
    }

    $variantValue = $data['variant_id']['source']['value'];
    $variantLabel = $component['variants'][$variantValue]['title'] ?? $variantValue;

    if (empty($variantLabel)) {
      return [];
    }

    return [new TranslatableMarkup('Variant: @variant', ['@variant' => $variantLabel])];
  }

  /**
   * Builds props summary items, from each prop source's own summary.
   *
   * @param array $component
   *   The component definition.
   * @param array $data
   *   The component data.
   *
   * @return array
   *   Summary items for props.
   */
  private function buildPropsSummary(array $component, array $data): array {
    $items = [];
    $properties = $component['props']['properties'] ?? [];

    foreach ($data['props'] ?? [] as $prop_id => $prop_data) {
      // A prop with no source ID was never configured. Resolving it anyway
      // would summarize the prop definition's default value, which the user
      // did not choose.
      if (empty($prop_data['source_id'])) {
        continue;
      }

      try {
        $source = $this->sourceManager->getSource($prop_id, $properties[$prop_id] ?? [], $prop_data, $this->context);
      }
      catch (PluginException $exception) {
        // The source plugin's module is gone, but the stored config remains.
        continue;
      }

      foreach ($source?->settingsSummary() ?? [] as $item) {
        $items[] = new TranslatableMarkup('@prop: @value', [
          '@prop' => $properties[$prop_id]['title'] ?? $prop_id,
          '@value' => $item,
        ]);
      }
    }

    return $items;
  }

}
