<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\display_builder\EmptyPlaceholderHelpInterface;
use Drupal\display_builder\SourceProcessingDataInterface;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewExposedSource as UiPatternsViewExposedSource;

/**
 * The exposed form of a view display, in a builder, with its options.
 *
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewExposedSource extends UiPatternsViewExposedSource implements EmptyPlaceholderHelpInterface, SourceProcessingDataInterface {

  use ViewsBuilderSourceTrait;

  /**
   * {@inheritdoc}
   */
  protected function getViewsPluginType(): ?string {
    return 'exposed_form';
  }

}
