<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Block rendering a correct HTML fragment.
 *
 * Used by BuilderPanelTest.
 */
#[Block(
  id: 'display_builder_test_correct',
  admin_label: new TranslatableMarkup('Correct renderable'),
)]

class CorrectRenderable extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => 'This renderable can be printed without any alteration.',
    ];
  }

}
