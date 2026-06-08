<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the display_builder_page_layout module.
 */
class PageLayoutHook {

  /**
   * Preprocess theme variables for HTML templates.
   *
   * @param array $variables
   *   The variables array (modify in place).
   *
   * @see \Drupal\display_builder_page_layout\Plugin\DisplayVariant\FullPageBuilderPageVariant
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    unset($variables['page']['content']['page_title']);
  }

}
