<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Block rendering an empty HTML fragment.
 *
 * Used by BuilderPanelTest.
 */
#[Block(
  id: 'display_builder_test_empty',
  admin_label: new TranslatableMarkup('Empty renderable'),
)]
class EmptyRenderable extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [];
  }

}
