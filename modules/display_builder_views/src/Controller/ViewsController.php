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
    // Disable cache page.
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore

    // The view here is not a "real" View storage, but the copy from the
    // tempstore provided by `view_ui` module. So, we have access to the state
    // not yet saved in config.
    $view = $view->getExecutable();
    $view->setDisplay($display);
    $extenders = $view->getDisplay()->getExtenders();

    if (!isset($extenders['display_builder'])) {
      return [];
    }
    /** @var \Drupal\display_builder\WithDisplayBuilderInterface $extender */
    $extender = $extenders['display_builder'];

    return $this->renderBuilder($extender);
  }

}
