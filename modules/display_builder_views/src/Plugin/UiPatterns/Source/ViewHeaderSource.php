<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\display_builder\EmptyPlaceholderHelpInterface;
use Drupal\display_builder\SourceProcessingDataInterface;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewHeaderSource as UiPatternsViewHeaderSource;

/**
 * The header of a view display, in a builder.
 *
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewHeaderSource extends UiPatternsViewHeaderSource implements EmptyPlaceholderHelpInterface, SourceProcessingDataInterface {

  use ViewsBuilderSourceTrait;

}
