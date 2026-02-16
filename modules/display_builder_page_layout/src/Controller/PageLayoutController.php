<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Controller;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Controller\IntegrationControllerBase;
use Drupal\display_builder_page_layout\PageLayoutInterface;

/**
 * Returns responses for Display Builder ui routes.
 */
class PageLayoutController extends IntegrationControllerBase {

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
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);

    return $this->renderBuilder($buildable);
  }

  /**
   * Creates a duplicate of a page layout and presents the add page layout form.
   *
   * This function serves as the controller for the 'duplicate-form' route
   * (e.g., admin/structure/page-layout/my_page_layout/duplicate).
   * It duplicates the provided page layout, assigns a new label
   * (e.g., "Duplicate of Original Label"), and then redirects the user
   * to the entity creation form to save the new page layout.
   *
   * @param \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout
   *   The page layout entity to duplicate.
   *
   * @return array
   *   The entity form for the duplicated entity, in 'add' mode.
   */
  public function duplicate(PageLayoutInterface $page_layout) {
    $duplicate = $page_layout->createDuplicate();
    $duplicate->set('label', $this->t('Duplicate of @label', ['@label' => $page_layout->label()]));

    return $this->entityFormBuilder()->getForm($duplicate, 'add');
  }

}
