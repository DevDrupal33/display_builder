<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\views\ViewExecutable;

/**
 * Replacement class for the ui_patterns_views source of the same id.
 *
 * Carries no #[Source] of its own on purpose. The plugin id already exists,
 * and two attributes claiming it would leave which definition wins up to the
 * order modules are scanned in. The definition stays upstream's; only the
 * class is ours.
 *
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewRowsSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    $style = $view->getStyle();
    $style->preRender($view->result);

    return (!empty($view->result) || $style->evenEmpty()) ? $style->render() : [];
  }

  /**
   * {@inheritdoc}
   *
   * The rows are the display's whole result area, not a strip.
   */
  protected function regionSize(): string {
    return 'lg';
  }

}
