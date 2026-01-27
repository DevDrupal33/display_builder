<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Layers island plugin implementation.
 */
#[Island(
  id: 'tree',
  label: new TranslatableMarkup('Tree'),
  description: new TranslatableMarkup('Hierarchical view of components and blocks.'),
  type: IslandType::View,
  default_region: 'main',
  icon: 'bar-chart-steps',
)]
class TreePanel extends BuilderPanel {

  /**
   * Proxy for slot source operations.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->slotSourceProxy = $container->get('display_builder.slot_sources_proxy');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 't',
      'help' => t('Show the tree'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:panel_tree',
      '#slots' => [
        'items' => $this->digFromSlot($builder_id, $data),
      ],
      '#attributes' => [
        // Required for JavaScript @see components/dropzone/dropzone.js.
        'data-db-id' => $builder_id,
        'data-node-title' => $this->t('Base container'),
        'data-db-root' => TRUE,
        // 'class' => ['db-dropzone--root', 'db-dropzone'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleComponent(string $builder_id, string $instance_id, SourceWithSlotsInterface $source, array $data, int $index = 0): ?array {
    $component_id = NULL;
    $label = $source->label();

    if ($source instanceof SourceWithChoicesInterface) {
      $component_id = $source->getChoice($data['source']);
      $label = $this->slotSourceProxy->getLabelWithSummary($data, [])['label'];
    }
    $instance_id = $instance_id ?: $data['node_id'];

    if (!$instance_id || !$component_id) {
      return NULL;
    }

    $slots = [];

    foreach ($source->getSlotDefinitions() as $slot_id => $definition) {
      $items = [
        '#type' => 'component',
        '#component' => 'display_builder:tree_item',
        '#props' => [
          'icon' => 'box-arrow-in-right',
        ],
        '#slots' => [
          'title' => $definition['title'],
        ],
        // Slot is needed for contextual menu paste.
        // @see assets/js/contextual_menu.js
        '#attributes' => [
          'data-slot-id' => $slot_id,
          'data-slot-title' => $definition['title'],
          'data-node-id' => $instance_id,
          'data-node-title' => $label,
          'data-menu-type' => 'slot',
        ],
      ];

      if ($sources = $source->getSlotValue($slot_id)) {
        $items['#slots']['children'] = $this->digFromSlot($builder_id, $sources);
      }

      $slots[] = $items;
    }

    // I f a single item, expand by default.
    if (\count($slots) === 1) {
      $slots[0]['#props']['expanded'] = TRUE;
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:tree_item',
      '#props' => [
        'expanded' => TRUE,
        'icon' => 'box',
      ],
      '#slots' => [
        'title' => $label,
        'children' => $slots,
      ],
      // Required for the context menu label.
      // @see assets/js/contextual_menu.js
      '#attributes' => [
        'data-node-id' => $instance_id,
        'data-node-title' => $label,
        'data-slot-position' => $index,
        'data-menu-type' => 'component',
        // 'class' => ['db-dropzone', 'db-tree__component'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): array {
    $instance_id = $instance_id ?: $data['node_id'];
    $label = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);

    if (isset($data['source_id']) && $data['source_id'] === 'entity_field') {
      $label['summary'] = (string) $this->t('Field: @label', ['@label' => $label['label']]);
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:tree_item',
      '#props' => [
        'icon' => 'view-list',
      ],
      '#slots' => [
        'title' => $label['summary'],
      ],
      '#attributes' => [
        'data-node-id' => $instance_id,
        // This label is used for contextual menu.
        // @see assets/js/contextual_menu.js
        'data-node-title' => $label['summary'],
        'data-slot-position' => $index,
        'data-menu-type' => 'block',
        // 'class' => ['db-dropzone', 'db-tree__block'],
      ],
    ];
  }

}
