<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\ThirdPartySettingsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Layers island plugin implementation.
 */
#[Island(
  id: 'layers',
  label: new TranslatableMarkup('Layers'),
  description: new TranslatableMarkup('Manage hierarchical layer view of elements without preview.'),
  type: IslandType::View,
  icon: 'layers',
)]
class LayersPanel extends BuilderPanel {

  /**
   * Proxy for slot source operations.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * Island plugins manager.
   */
  protected IslandPluginManagerInterface $islandManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->slotSourceProxy = $container->get('display_builder.slot_sources_proxy');
    $instance->islandManager = $container->get('plugin.manager.db_island');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'y' => t('Show the layer'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $build = parent::build($builder, $data, $options);

    if (empty($build['#slots']['content'] ?? [])) {
      // Load en empty component to have any assets with it.
      $build['#slots']['content'] = [
        '#type' => 'component',
        '#component' => 'display_builder:layer',
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleComponent(string $builder_id, string $instance_id, array $data, int $index = 0): array {
    $component_id = $data['source']['component']['component_id'] ?? NULL;
    $instance_id = $instance_id ?: $data['node_id'];

    if (!$instance_id && !$component_id) {
      return [];
    }

    $component = $this->sdcManager->getDefinition($component_id);

    if (!$component) {
      return [];
    }

    $slots = [];

    foreach ($component['slots'] ?? [] as $slot_id => $definition) {
      $dropzone = [
        '#type' => 'component',
        '#component' => 'display_builder:dropzone',
        '#props' => [
          'title' => $definition['title'],
          'variant' => 'highlighted',
        ],
        '#attributes' => [
          // Required for JavaScript @see components/dropzone/dropzone.js.
          'data-db-id' => $builder_id,
          // Slot is needed for contextual menu paste.
          // @see assets/js/contextual_menu.js
          'data-slot-id' => $slot_id,
          'data-slot-title' => $definition['title'],
          'data-node-title' => $component['label'],
        ],
      ];

      if (isset($data['source']['component']['slots'][$slot_id]['sources'])) {
        $sources = $data['source']['component']['slots'][$slot_id]['sources'];
        $dropzone['#slots']['content'] = $this->digFromSlot($builder_id, $sources);
      }
      $dropzone = $this->htmxEvents->onSlotDrop($dropzone, $builder_id, $this->getPluginID(), $instance_id, $slot_id);
      $slots[] = [
        [
          '#plain_text' => $definition['title'],
        ],
        $dropzone,
      ];
    }
    $name = $component['name'];
    $variant = $this->getComponentVariantLabel($data, $component);

    if ($variant) {
      $name = \sprintf('%s - %s', $name, $variant);
    }

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:layer',
      '#slots' => [
        'title' => $name,
        'children' => $slots,
      ],
      // Required for the context menu label.
      // @see assets/js/contextual_menu.js
      '#attributes' => [
        'data-node-title' => $name,
      ],
    ];
    $build = $this->addThirdPartySettingsSummary($data, $build);
    $build = $this->addComponentSettingsSummary($data, $component, $build);
    $build = $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, (string) $component['label'], $index);

    return $this->applyThirdPartySettingsToRenderable($build, $data, $builder_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): array {
    $label = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);

    if (isset($data['source_id']) && $data['source_id'] === 'entity_field') {
      $label['summary'] = (string) $this->t('Field: @label', ['@label' => $label['label']]);
    }

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:layer',
      '#slots' => [
        'title' => $label['summary'],
      ],
    ];
    $instance_id = $instance_id ?: $data['node_id'];

    $build = $this->addThirdPartySettingsSummary($data, $build);

    // This label is used for contextual menu.
    // @see assets/js/contextual_menu.js
    $build['#attributes']['data-node-title'] = $label['summary'];
    $build['#attributes']['data-slot-position'] = $index;
    $build = $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $label['summary'], $index);

    return $this->applyThirdPartySettingsToRenderable($build, $data, $builder_id);
  }

  /**
   * Get the label for a component variant.
   *
   * @param array $data
   *   The component data array.
   * @param array $definition
   *   The component definition array.
   *
   * @return string
   *   The variant label or empty string if no variant is set.
   */
  private function getComponentVariantLabel(array $data, array $definition): string {
    if (!isset($data['source']['component']['variant_id'])) {
      return '';
    }

    if ($data['source']['component']['variant_id']['source_id'] !== 'select') {
      return '';
    }
    $variant_id = $data['source']['component']['variant_id']['source']['value'] ?? '';

    if (empty($variant_id)) {
      return '';
    }

    return $definition['variants'][$variant_id]['title'] ?? '';
  }

  /**
   * Add third party settings summary to layer's info slot.
   *
   * @param array $data
   *   The node data.
   * @param array $build
   *   The layer component renderable array.
   *
   * @return array
   *   The layer component renderable array.
   */
  private function addThirdPartySettingsSummary(array $data, array $build): array {
    if (!isset($data['third_party_settings'])) {
      return $build;
    }

    foreach ($data['third_party_settings'] as $provider => $settings) {
      // In Display Builder, third_party_settings providers can be:
      // - an island plugin ID (our 'normal' way)
      // - a Drupal module name (the Drupal way, found in displays imported and
      // converted, not leveraged by us for now but we may do it later).
      // So, let's check the plugin ID exists before running logic.
      if (!$this->islandManager->hasDefinition($provider)) {
        continue;
      }
      $island = $this->islandManager->createInstance($provider, $settings);

      if ($island instanceof ThirdPartySettingsInterface && $summary = $island->getSummary()) {
        $build['#slots']['info'] = \array_merge($build['#slots']['info'] ?? [], $summary);
      }
    }

    return $build;
  }

  /**
   * Add config settings summary to layer's info slot.
   *
   * @param array $data
   *   The node data.
   * @param array $component
   *   The component definition.
   * @param array $build
   *   The layer component renderable array.
   *
   * @return array
   *   The layer component renderable array.
   */
  private function addComponentSettingsSummary(array $data, array $component, array $build): array {
    if (!isset($data['source_id']) || !isset($data['source']['component']['props']) || $data['source_id'] !== 'component') {
      return $build;
    }

    $items = [];

    foreach ($data['source']['component']['props'] as $source_id => $source) {
      if (!isset($source['source']['value']) || $source['source']['value'] === '') {
        continue;
      }

      $label = $component['props']['properties'][$source_id]['title'] ?? '';
      $value = $source['source']['value'];

      if (\is_array($value)) {
        $value = \trim(\implode(', ', $value), ', ');
      }
      $item = \sprintf('%s %s', $label, $value);
      $items[] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        '#value' => $item,
      ];
    }

    if (empty($items)) {
      return $build;
    }

    $summary = [
      [
        '#type' => 'html_tag',
        '#tag' => 'em',
        '#value' => new TranslatableMarkup('Config'),
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'ul',
        '#attributes' => [
          'class' => ['summary'],
        ],
        0 => $items,
      ],
    ];

    $build['#slots']['info'] = \array_merge($build['#slots']['info'] ?? [], $summary);

    return $build;
  }

}
