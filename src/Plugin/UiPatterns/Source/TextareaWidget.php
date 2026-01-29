<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\WysiwygWidget;

use function Symfony\Component\String\u;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'textarea',
  label: new TranslatableMarkup('Textarea'),
  description: new TranslatableMarkup('Textarea without editor'),
  prop_types: ['slot'],
  tags: ['widget'],
  metadata: ['group' => new TranslatableMarkup('Utilities')]
)]
class TextareaWidget extends WysiwygWidget implements TrustedCallbackInterface {

  private const HTML_FORMAT = 'display_builder_html';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['textFormat'];
  }

  /**
   * Customize the text_format element.
   *
   * @param array $element
   *   Element to process.
   *
   * @return array
   *   Processed element
   */
  public static function textFormat(array $element): array {
    if (!isset($element['#ui_patterns']) || !$element['#ui_patterns']) {
      return $element;
    }

    if (isset($element['format']['format']['#access'])
      && !$element['format']['format']['#access']) {
      // See code at Drupal\filter\Element\TextFormat::processTextFormat()
      // when the format is not accessible, we need to make it accessible.
      $element['format']['format']['#access'] = TRUE;
    }

    if (isset($element['format']['format']['#options'])) {
      foreach (\array_keys($element['format']['format']['#options']) as $key) {
        if ($key !== self::HTML_FORMAT) {
          unset($element['format']['format']['#options'][$key]);
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $value = $this->getSetting('value')['value'] ?? '';

    return [
      u(\strip_tags($value))->truncate(20, '...', FALSE),
    ];
  }

}
