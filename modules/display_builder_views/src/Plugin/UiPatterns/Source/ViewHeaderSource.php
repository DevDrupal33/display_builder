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
  id: 'view_header',
  label: new TranslatableMarkup('[View] Header'),
  context_requirements: ['is_display_builder_views'],
  prop_types: ['slot'],
  tags: ['views'],
)]
class ViewHeaderSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  public static function setVariableId(): string {
    return 'header';
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();

    // This is for builder and preview.
    if (!$view) {
      return $this->t('View Header placeholder');
    }

    if (!$view->header || empty($view->header)) {
      return '';
    }

    $output = [];

    foreach ($view->header as $key => $value) {
      $output[$key] = $value->render();
    }

    return $output;
  }

}
