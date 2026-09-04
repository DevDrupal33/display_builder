<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\display_builder\EmptyPlaceholderHelpInterface;
use Drupal\display_builder\SourceProcessingDataInterface;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewPagerSource as UiPatternsViewPagerSource;

/**
 * The pager of a view display, in a builder, with the pager options.
 *
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewPagerSource extends UiPatternsViewPagerSource implements EmptyPlaceholderHelpInterface, SourceProcessingDataInterface {

  use ViewsBuilderSourceTrait;

  /**
   * {@inheritdoc}
   */
  protected function getViewsPluginType(): ?string {
    return 'pager';
  }

}
