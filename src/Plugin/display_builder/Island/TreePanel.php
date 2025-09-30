<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\SlotSourceProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Layers island plugin implementation.
 */
#[Island(
  id: 'tree',
  label: new TranslatableMarkup('Tree'),
  description: new TranslatableMarkup('Manageable hierarchical tree view of elements.'),
  type: IslandType::View,
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
      't' => t('Show the tree'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    return [
      '#type' => 'component',
      '#component' => 'display_builder:panel_tree',
      '#slots' => [
        'items' => $this->digFromSlot($builder_id, $data),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSingleComponent(string $builder_id, string $instance_id, array $data, int $index = 0): array {
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
          'data-node-title' => $component['label'],
          'data-menu-type' => 'slot',
        ],
      ];

      if (isset($data['source']['component']['slots'][$slot_id]['sources'])) {
        $sources = $data['source']['component']['slots'][$slot_id]['sources'];
        $items['#slots']['children'] = $this->digFromSlot($builder_id, $sources);
      }

      $slots[] = $items;
    }

    // I f a single item, expand by default.
    if (\count($slots) === 1) {
      $slots[0]['#props']['expanded'] = TRUE;
    }

    $name = $component['name'];
    $variant = $this->getComponentVariantLabel($data, $component);

    if ($variant) {
      $name .= ' - ' . $variant;
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:tree_item',
      '#props' => [
        'expanded' => TRUE,
        'icon' => 'box',
      ],
      '#slots' => [
        'title' => $name,
        'children' => $slots,
      ],
      // Required for the context menu label.
      // @see assets/js/contextual_menu.js
      '#attributes' => [
        'data-node-id' => $instance_id,
        'data-node-title' => $name,
        'data-slot-position' => $index,
        'data-menu-type' => 'component',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): array {
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
      ],
    ];
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

}
