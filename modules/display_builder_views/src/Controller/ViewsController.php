<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Controller;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Controller\IntegrationControllerBase;
use Drupal\views\ViewEntityInterface;

/**
 * Returns responses for Display Builder ui routes.
 */
class ViewsController extends IntegrationControllerBase {

  /**
   * Provides a generic title callback for a display used in pages.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view to be edited.
   * @param string $display
   *   The display ID being edited.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the display page, if found.
   */
  public function title(ViewEntityInterface $view, string $display): TranslatableMarkup {
    $params = [
      '@view' => $view->label(),
      '@display' => $view->getDisplay($display)['display_title'] ?? '',
    ];

    return $this->t('Display builder for @view @display', $params);
  }

  /**
   * Load the display builder for views.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view to be edited.
   * @param string $display
   *   The display ID being edited.
   *
   * @return array
   *   The display builder renderable.
   */
  public function getBuilder(ViewEntityInterface $view, string $display): array {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance(
      'view_display',
      ['view_id' => $view->id(), 'view_display' => $display]
    );

    return $this->renderBuilder($buildable);
  }

}
