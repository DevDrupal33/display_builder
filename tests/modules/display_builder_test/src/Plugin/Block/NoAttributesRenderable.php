<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Block rendering an HTML fragment without #attributes support.
 *
 * Used by BuilderPanelTest.
 */
#[Block(
  id: 'display_builder_test_no_attributes',
  admin_label: new TranslatableMarkup('No attributes renderable'),
)]
class NoAttributesRenderable extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '#markup has no support for #attributes',
    ];
  }

}
