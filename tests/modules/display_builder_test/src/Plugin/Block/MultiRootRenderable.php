<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Block rendering an HTML fragment with multiple root elements.
 *
 * Used by BuilderPanelTest.
 */
#[Block(
  id: 'display_builder_test_multi_root',
  admin_label: new TranslatableMarkup('Multi-root renderable'),
)]
class MultiRootRenderable extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Hello',
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'World',
      ],
    ];
  }

}
