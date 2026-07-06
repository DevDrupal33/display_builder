<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\SourceWithSlotsInterface;
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
  default_region: 'main',
  icon: 'layers',
)]
class LayersPanel extends BuilderPanel {

  /**
   * Island plugins manager.
   */
  protected IslandPluginManagerInterface $islandManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->islandManager = $container->get('plugin.manager.db_island');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'y',
      'help' => t('Show the layer'),
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
  protected function buildSingleComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, int $index = 0): ?array {
    $info = $this->resolveComponentInfo($source, $data, $node_id);

    if ($info === NULL) {
      return NULL;
    }

    ['label' => $label, 'instance_id' => $node_id] = $info;

    $slots = [];

    foreach ($source->getSlotDefinitions() as $slot_id => $definition) {
      $dropzone = [
        '#type' => 'component',
        '#component' => 'display_builder:dropzone',
        '#props' => [
          'title' => $definition['title'],
          'variant' => 'highlighted',
        ],
        '#attributes' => [
          // Required for JavaScript @see components/dropzone/dropzone.js.
          'data-db-id' => $instance->id(),
          // Slot is needed for contextual menu paste.
          // @see assets/js/contextual_menu.js
          'data-slot-id' => $slot_id,
          'data-slot-title' => $definition['title'],
          'data-node-id' => $node_id,
          'data-node-title' => $label,
          // @see https://playwright.dev/docs/locators#locate-by-test-id
          'data-testid' => $node_id . '_' . $slot_id,
        ],
      ];

      if ($sources = $source->getSlotValue($slot_id)) {
        $dropzone['#slots']['content'] = $this->digFromSlot($instance, $sources);
      }
      $dropzone = $this->htmxEvents->onSlotDrop($dropzone, (string) $instance->id(), $this->getPluginID(), $node_id, $slot_id);
      $slots[] = [
        [
          '#plain_text' => $definition['title'],
        ],
        $dropzone,
      ];
    }

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:layer',
      '#slots' => [
        'title' => $label,
        'children' => $slots,
      ],
      // Required for the context menu label.
      // @see assets/js/contextual_menu.js
      '#attributes' => [
        'data-node-title' => $label,
        'data-node-id' => $node_id,
        // @see https://playwright.dev/docs/locators#locate-by-test-id
        'data-testid' => $node_id,
      ],
    ];
    $build = $this->addThirdPartySettingsSummary($data, $build);
    $build = $this->addComponentSettingsSummary($source, $build);

    return $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $source->label(), $index);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(InstanceInterface $instance, string $node_id, array $data, int $index = 0): array {
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

    $node_id = $node_id ?: $data['node_id'] ?? NULL;

    if (!$node_id) {
      $this->logger->error('[LayersPanel::buildSingleBlock] missing instance ID. <pre>' . \print_r($data, TRUE) . '</pre>');

      return $build;
    }

    $build = $this->addThirdPartySettingsSummary($data, $build);

    // This label is used for contextual menu.
    // @see assets/js/contextual_menu.js
    $build['#attributes']['data-node-title'] = $label['summary'];
    $build['#attributes']['data-slot-position'] = $index;
    // @see https://playwright.dev/docs/locators#locate-by-test-id
    $build['#attributes']['data-testid'] = $node_id;

    // Add data-node-type for easier identification of block types in JS, CSS or
    // tests.
    if (isset($data['source_id'])) {
      $build['#attributes']['data-node-type'] = $data['source_id'];
    }

    return $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $label['summary'], $index);
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
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $build
   *   The layer component renderable array.
   *
   * @return array
   *   The layer component renderable array.
   */
  private function addComponentSettingsSummary(SourceWithSlotsInterface $source, array $build): array {
    $items = [];

    foreach ($source->settingsSummary() as $item) {
      if ($item !== NULL) {
        $items[] = [
          '#type' => 'html_tag',
          '#tag' => 'li',
          '#value' => $item,
        ];
      }
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
