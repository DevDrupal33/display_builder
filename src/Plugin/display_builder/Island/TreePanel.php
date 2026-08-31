<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\SourceWithSlotsInterface;

/**
 * Navigator island plugin implementation.
 */
#[Island(
  id: 'tree',
  label: new TranslatableMarkup('Navigator'),
  description: new TranslatableMarkup('Hierarchical schematic view of components and blocks.'),
  type: IslandType::View,
  region: 'sidebar',
)]
class TreePanel extends ViewPanelBase {

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'n',
      'help' => \t('Show the navigator'),
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
      '#props' => [
        'collapse_label' => (string) $this->t('Collapse all'),
        'expand_label' => (string) $this->t('Expand all'),
      ],
      '#slots' => [
        'items' => $this->digFromSlot($builder, $data),
      ],
      '#attributes' => [
        // Required for JavaScript @see components/dropzone/dropzone.js.
        'data-db-id' => $builder_id,
        'data-node-title' => $this->t('Base container'),
        'data-db-root' => TRUE,
        // Shown by panel_tree.css when the dropzone is actually empty.
        'data-empty-hint' => $this->t('Empty'),
      ],
    ];

    return $this->htmxEvents->onRootDrop($build, $builder_id, $this->getPluginID());
  }

  /**
   * {@inheritdoc}
   */
  protected function renderComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, string $component_id, string $label, int $index): ?array {
    $builder_id = (string) $instance->id();
    $slots = [];

    foreach ($source->getSlotDefinitions() as $slot_id => $definition) {
      $dropzone = [
        '#type' => 'component',
        '#component' => 'display_builder:dropzone',
        '#attributes' => [
          // Required for JavaScript @see components/dropzone/dropzone.js.
          'data-db-id' => $builder_id,
          'data-testid' => 'dropzone_' . $slot_id,
        ],
      ];

      if ($sources = $source->getSlotValue($slot_id)) {
        $dropzone['#slots']['content'] = $this->digFromSlot($instance, $sources);
      }
      else {
        // Shown by panel_tree.css when the dropzone is actually empty.
        $dropzone['#attributes']['data-empty-hint'] = $this->t('Empty');
      }
      $dropzone = $this->htmxEvents->onSlotDrop($dropzone, $builder_id, $this->getPluginID(), $node_id, $slot_id);

      $slots[] = [
        '#type' => 'component',
        '#component' => 'display_builder:tree_node',
        '#props' => [
          'icon' => 'box-arrow-in-right',
        ],
        '#slots' => [
          'title' => $definition['title'],
          'children' => [$dropzone],
        ],
        '#attributes' => \array_merge(
          ['data-menu-type' => 'slot'],
          $this->buildSlotAttributes($slot_id, $definition['title'], $node_id, $label)
        ),
      ];
    }

    // If a single slot, expand it by default.
    if (\count($slots) === 1) {
      $slots[0]['#props']['expanded'] = TRUE;
    }

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:tree_node',
      '#props' => [
        'expanded' => TRUE,
        'icon' => 'box',
      ],
      '#slots' => [
        'title' => $label,
        'children' => $slots,
      ],
      '#attributes' => \array_merge(
        ['data-node-id' => $node_id, 'data-menu-type' => 'component'],
        $this->buildNodeAttributes($label, $index)
      ),
    ];

    return $this->htmxEvents->onInstanceClick($build, $builder_id, $node_id, $source->label(), $index);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(InstanceInterface $instance, string $node_id, array $data, int $index = 0): array {
    $node_id = $node_id ?: $data['node_id'];
    $label = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);

    if (isset($data['source_id']) && $data['source_id'] === 'entity_field') {
      $label['summary'] = (string) $this->t('Field: @label', ['@label' => $label['label']]);
    }

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:tree_node',
      '#props' => [
        'icon' => 'view-list',
      ],
      '#slots' => [
        'title' => $label['summary'],
      ],
      '#attributes' => \array_merge(
        ['data-node-id' => $node_id, 'data-menu-type' => 'block'],
        $this->buildNodeAttributes($label['summary'], $index, $data['source_id'] ?? NULL)
      ),
    ];

    return $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $label['summary'], $index);
  }

}
