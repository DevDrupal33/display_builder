<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\display_builder\Plugin\UiPatterns\Source\TextareaWidget;

/**
 * Hook implementations for display_builder.
 */
class ElementAlterHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    if (isset($info['text_format'])) {
      $info['text_format']['#pre_render'][] = [TextareaWidget::class, 'textFormat'];
    }
  }

}
