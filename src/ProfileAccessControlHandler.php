<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the profile entity type.
 */
final class ProfileAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\display_builder\Entity\Profile $entity */
    $access = parent::checkAccess($entity, $operation, $account);

    if ($access->isAllowed()) {
      // Permission to administer profiles gives permission to use all profiles.
      return $access;
    }

    if ($operation === 'view') {
      return AccessResult::allowedIfHasPermission($account, $entity->getPermissionName());
    }

    return AccessResult::forbidden();
  }

}
