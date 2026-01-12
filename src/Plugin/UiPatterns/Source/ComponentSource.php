<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

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
    $component = $this->getSetting('component');
    $component_id = $component['component_id'] ?? '';

    if (!$component_id) {
      return [];
    }
    $definition = $this->componentManager->getDefinition($component_id);

    return $definition['slots'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValues(): array {
    $slots = [];
    $component = $this->getSetting('component');

    // Remove this weird 'sources' level.
    foreach ($component['slots'] ?? [] as $slot_id => $slot) {
      if (isset($slot['sources'])) {
        $slots[$slot_id] = $slot['sources'];
      }
    }

    return $slots;
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

    if (!isset($data['props'])) {
      return [];
    }
    $items = [];

    $component_id = $data['component_id'];
    $component = $this->componentManager->getDefinition($component_id);

    foreach ($data['props'] as $source_id => $source) {
      if (!isset($source['source']['value']) || $source['source']['value'] === '') {
        continue;
      }

      $label = $component['props']['properties'][$source_id]['title'] ?? '';
      $value = $source['source']['value'];

      if (\is_array($value)) {
        $value = \trim(\implode(', ', $value), ', ');
      }
      $items[] = \sprintf('%s %s', $label, $value);
    }

    return $items;
  }

}
