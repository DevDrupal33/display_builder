<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Layers island plugin implementation.
 */
#[Island(
  id: 'layers',
  label: new TranslatableMarkup('Layers'),
  description: new TranslatableMarkup('Manageable hierarchical layer view of elements.'),
  type: IslandType::View,
  keyboard_shortcuts: [
    'y' => new TranslatableMarkup('Show layers view'),
  ],
  icon: 'layers',
)]
class LayersPanel extends BuilderPanel {

  /**
   * Proxy for slot source operations.
   *
   * @var \Drupal\display_builder\SlotSourceProxy
   */
  protected $slotSourceProxy;

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
  public function build(string $builder_id, array $data, array $options = []): array {
    $build = parent::build($builder_id, $data, $options);

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
    $instance_id = $instance_id ?: $data['_instance_id'];

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
          'data-instance-title' => $component['label'],
        ],
      ];

      if (isset($data['source']['component']['slots'][$slot_id]['sources'])) {
        $sources = $data['source']['component']['slots'][$slot_id]['sources'];
        $dropzone['#slots']['content'] = $this->digFromSlot($builder_id, $sources);
      }
      $dropzone = $this->htmxEvents->onSlotDrop($dropzone, $builder_id, $instance_id, $slot_id);
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
      $name .= ' - ' . $variant;
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
        'data-instance-title' => $name,
      ],
    ];

    return $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, (string) $component['label'], $index);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): array {
    $label = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:layer',
      '#slots' => [
        'title' => $label['summary'],
      ],
    ];
    $instance_id = $instance_id ?: $data['_instance_id'];

    // This label is used for contextual menu.
    // @see assets/js/contextual_menu.js
    $build['#attributes']['data-instance-title'] = $label['summary'];
    $build['#attributes']['data-slot-position'] = $index;

    return $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $label['label'], $index);
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
