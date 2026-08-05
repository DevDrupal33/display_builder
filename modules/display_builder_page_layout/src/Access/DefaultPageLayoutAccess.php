<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Allows creating the default page layout while the site has none.
 *
 * A page layout with no condition matches every page, so the UI only ever
 * offers one. Guarding the route rather than the form also hides the action
 * link, because the local action manager checks route access.
 *
 * @see \Drupal\display_builder_page_layout\PageLayoutInterface::isDefault()
 */
final class DefaultPageLayoutAccess implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks that the site has no default page layout yet.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    $storage = $this->entityTypeManager->getStorage('page_layout');
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface[] $page_layouts */
    $page_layouts = $storage->loadMultiple();

    foreach ($page_layouts as $page_layout) {
      if ($page_layout->isDefault()) {
        return AccessResult::forbidden()->addCacheTags($storage->getEntityType()->getListCacheTags());
      }
    }

    return AccessResult::allowed()->addCacheTags($storage->getEntityType()->getListCacheTags());
  }

}
