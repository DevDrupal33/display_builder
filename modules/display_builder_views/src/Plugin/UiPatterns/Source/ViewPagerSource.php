<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\ui_patterns\Attribute\Source;

/**
 * Plugin implementation of the source for views.
 */
#[Source(
  id: 'view_pager',
  label: new TranslatableMarkup('[View] Pager'),
  context_requirements: ['is_display_builder_views'],
  prop_types: ['slot'],
  tags: ['views'],
)]
class ViewPagerSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  public static function setVariableId(): string {
    return 'pager';
  }

}
