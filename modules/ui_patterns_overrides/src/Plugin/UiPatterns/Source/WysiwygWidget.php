<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_overrides\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\WysiwygWidget as Upstream;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'wysiwyg',
  label: new TranslatableMarkup('Wysiwyg'),
  description: new TranslatableMarkup('Wysiwyg editor'),
  prop_types: ['slot'],
  tags: ['widget']
)]
class WysiwygWidget extends Upstream implements SourcesBundlerInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'value' => [
        'value' => '',
        'format' => 'basic_html',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    return [
      '#type' => 'processed_text',
      '#text' => $this->getSetting('value')['value'],
      '#format' => $this->getSetting('value')['format'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSkeleton(string $item_id): array {
    return [
      'source_id' => 'wysiwyg',
      'source' => [
        'source' => [
          'value' => '',
          'format' => 'basic_html',
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
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    // @todo implement
    return [];
  }

}
