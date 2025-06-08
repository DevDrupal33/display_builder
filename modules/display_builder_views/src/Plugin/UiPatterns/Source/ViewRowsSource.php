<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\ui_patterns\Attribute\Source;

/**
 * Plugin implementation of the source for views.
 *
 * @see Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewRowsSource
 * @see display_builder_views.module
 */
#[Source(
  id: 'View_rows_source',
  label: new TranslatableMarkup('View rows'),
  description: new TranslatableMarkup('The Views rows area.'),
  context_requirements: ['is_display_builder_views'],
  prop_types: ['slot'],
  tags: ['views'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view', label: new TranslatableMarkup('View')),
  ]
)]
class ViewRowsSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  public static function setVariableId(): string {
    return 'rows';
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    // Values are injected as context from
    // display_builder_views_preprocess_views_view.
    $view = $this->getView();

    // This is for builder and preview.
    if (!$view) {
      return $this->t('View rows placeholder');
    }

    $rows = $this->getContextValue('ui_patterns_views:variables')['rows'] ?? NULL;

    // From ViewRowsSource::getPropValue().
    // $rows = $this->getContextValue('ui_patterns_views:rows');.
    if (!\is_array($rows) || \count($rows) < 1) {
      if (!$view->empty || empty($view->empty)) {
        return [];
      }
      $output = [];

      foreach ($view->empty as $key => $value) {
        $output[$key] = $value->render();
      }

      return $output;
    }

    // Return the view rows.
    return $this->renderOutput($rows);
  }

}
