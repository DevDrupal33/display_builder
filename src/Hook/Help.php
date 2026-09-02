<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides the module's help page entry.
 */
class Help {

  use StringTranslationTrait;

  private const string DOCS_URL = 'https://display-builder-b6bde3.pages.drupalcode.org';

  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name): string {
    if ($route_name !== 'help.page.display_builder') {
      return '';
    }

    return '<p>' . $this->t('Display Builder provides a unified, drag-and-drop interface for building entity displays, page layouts, and Views displays out of reusable components. See the <a href=":url">online documentation</a> for setup, concepts, and screenshots.', [':url' => self::DOCS_URL]) . '</p>';
  }

}
