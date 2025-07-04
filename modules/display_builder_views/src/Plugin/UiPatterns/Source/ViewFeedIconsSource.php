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
  id: 'view_feed_icons',
  label: new TranslatableMarkup('[View] Feed icons'),
  context_requirements: ['is_display_builder_views'],
  prop_types: ['slot'],
  tags: ['views'],
)]
class ViewFeedIconsSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  public static function setVariableId(): string {
    return 'feed_icons';
  }

}
