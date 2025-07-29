<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_page_layout\PageLayoutInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Display Builder ui routes.
 */
class PageLayoutController extends ControllerBase {

  public function __construct(
    protected StateManagerInterface $stateManager,
  ) {}

  /**
   * Returns a page title.
   *
   * @param \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout
   *   The page layout entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getTitle(PageLayoutInterface $page_layout): TranslatableMarkup {
    return $this->t('Build %label page layout', ['%label' => $page_layout->label()]);
  }

  /**
   * Load the display builder for page layout.
   *
   * @param \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout
   *   The page layout entity.
   *
   * @return array
   *   The display builder renderable.
   */
  public function getBuilder(PageLayoutInterface $page_layout): array {
    $display_builder = $page_layout->getDisplayBuilder();
    if (!$display_builder) {
      // Display Builder is not activated for this page layout. This is not
      // supposed to happen because Display Builder is mandatory.
      throw new NotFoundHttpException();
    }

    $instance_id = $page_layout->getInstanceId();
    if (!$this->stateManager->load($instance_id)) {
      // Display Builder instance was not created yet, or was deleted, for this
      // page layout.
      throw new NotFoundHttpException();
    }
    $contexts = $this->stateManager->getContexts($instance_id) ?? [];

    return $display_builder->build($instance_id, $contexts);
  }

}
