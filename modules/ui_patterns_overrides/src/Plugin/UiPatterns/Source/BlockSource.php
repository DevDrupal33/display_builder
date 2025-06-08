<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_overrides\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource as Upstream;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'block',
  label: new TranslatableMarkup('Block'),
  description: new TranslatableMarkup('A block plugin from an allow list.'),
  prop_types: ['slot']
)]
class BlockSource extends Upstream implements SourcesBundlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getDataSkeleton(string $item_id): array {
    return [
      'source_id' => 'block',
      'source' => [
        'plugin_id' => $item_id,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupedOptions(): array {
    $definitions = $this->getOptions();

    return parent::getBlockOptions($definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function getOption(string $item_id): array {
    if (!$options = $this->getOptions()) {
      return [];
    }

    return $options[$item_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionLabel(array $data): string|TranslatableMarkup {
    $block_id = $data['source']['plugin_id'];

    if (!$options = $this->getOptions()) {
      return $block_id;
    }

    return $options[$block_id]['admin_label'] ?? $block_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    return parent::listBlockDefinitions();
  }

}
