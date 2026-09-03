<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\ViewExecutable;

/**
 * Plugin implementation of the source for views.
 */
#[Source(
  id: 'view_exposed',
  label: new TranslatableMarkup('[View] Exposed form'),
  prop_types: ['slot'],
  tags: ['views'],
  context_requirements: ['views:style'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view'),
    'display' => new ContextDefinition('string'),
  ],
)]
class ViewExposedSource extends ViewsUiPatternsSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function renderFromView(ViewExecutable $view): mixed {
    // Avoid interfering with the admin forms.
    $route_name = (string) $this->routeMatch->getRouteName();

    if (\str_starts_with($route_name, 'views_ui.')) {
      return [];
    }
    $view->initHandlers();

    /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_form */
    $exposed_form = $view->getDisplay()->getPlugin('exposed_form');
    $build = $exposed_form->renderExposedForm(TRUE);
    $default_form_keys = ['actions', 'form_build_id', 'form_id', 'form_token'];

    // ::children() returns the key names as a list, so the names are the
    // values here, never the keys.
    if (empty(\array_diff(Element::children($build), $default_form_keys))) {
      return [];
    }

    if (!empty($build)) {
      $build += [
        '#view' => $view,
        '#display_id' => $view->current_display,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getViewsPluginType(): ?string {
    return 'exposed_form';
  }

}
