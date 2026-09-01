<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\ViewExecutable;

/**
 * Plugin implementation of the source for views.
 */
#[Source(
  id: 'view_header',
  label: new TranslatableMarkup('[View] Header'),
  prop_types: ['slot'],
  tags: ['views'],
  context_requirements: ['views:style'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view'),
    'display' => new ContextDefinition('string'),
  ],
)]
class ViewHeaderSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    return $view->getDisplay()->renderArea('header');
  }

}
