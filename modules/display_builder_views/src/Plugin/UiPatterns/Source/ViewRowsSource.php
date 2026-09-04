<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\display_builder\EmptyPlaceholderHelpInterface;
use Drupal\display_builder\SourceProcessingDataInterface;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewRowsSource as UiPatternsViewRowsSource;

/**
 * The rows of a view display, in a builder, with the style options.
 *
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewRowsSource extends UiPatternsViewRowsSource implements EmptyPlaceholderHelpInterface, SourceProcessingDataInterface {

  use ViewsBuilderSourceTrait;

  /**
   * {@inheritdoc}
   *
   * The whole result area, not a strip.
   */
  protected function regionSize(): string {
    return 'lg';
  }

  /**
   * {@inheritdoc}
   */
  protected function getViewsPluginType(): ?string {
    return 'style';
  }

}
