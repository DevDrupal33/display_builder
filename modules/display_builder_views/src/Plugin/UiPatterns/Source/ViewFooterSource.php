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
  id: 'view_footer',
  label: new TranslatableMarkup('[View] Footer area'),
  context_requirements: ['is_display_builder_views'],
  prop_types: ['slot'],
  tags: ['views'],
)]
class ViewFooterSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  public static function setVariableId(): string {
    return 'footer';
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();

    if (!$view) {
      return $this->t('View Footer placeholder');
    }

    if (!$view->footer || empty($view->footer)) {
      return '';
    }

    $output = [];

    foreach ($view->footer as $key => $value) {
      $output[$key] = $value->render();
    }

    return $output;
  }

}
