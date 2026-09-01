<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\views\ViewExecutable;

/**
 * Replacement class for the ui_patterns_views source of the same id.
 *
 * @see \Drupal\display_builder_views\Plugin\UiPatterns\Source\ViewRowsSource
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewTitleSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    return $view->getTitle();
  }

}
