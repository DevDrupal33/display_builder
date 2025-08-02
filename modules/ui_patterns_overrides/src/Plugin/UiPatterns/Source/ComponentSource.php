<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_overrides\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\ComponentSource as Upstream;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'component',
  label: new TranslatableMarkup('Component'),
  description: new TranslatableMarkup('Add a Component'),
  prop_types: ['slot']
)]
class ComponentSource extends Upstream implements SourcesBundlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getDataSkeleton(string $item_id): array {
    return [
      'source_id' => 'component',
      'source' => [
        'component' => [
          'component_id' => $item_id,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedOptions(): array {
    // @todo implement
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOption(string $item_id): array {
    // @todo implement
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionLabel(array $data): string|TranslatableMarkup {
    $component_id = $data['source']['component']['component_id'] ?? NULL;

    if (!$component_id) {
      return 'n/a';
    }

    return \Drupal::service('plugin.manager.sdc')->getDefinition($component_id)['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    // @todo implement
    return [];
  }

}
