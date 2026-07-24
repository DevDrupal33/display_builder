<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the instance entity type.
 */
final class InstanceAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new self(
      $entity_type,
      $container->get('plugin.manager.display_buildable'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\display_builder\InstanceInterface $entity */
    // 1. Profile-related access.
    $profile_result = $this->checkProfileAccess($entity, $account);

    if ($profile_result->isForbidden()) {
      return $profile_result;
    }
    // 2. Access to DisplayBuildableInterface implementation.
    $buildable_result = $this->checkBuildableAccess($entity, $account);

    return $profile_result->andIf($buildable_result);
  }

  /**
   * Checks permission to use the profile.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The entity for which to check access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function checkProfileAccess(InstanceInterface $instance, AccountInterface $account): AccessResultInterface {
    if ($profile = $instance->getProfile()) {
      return $profile->access('view', $account, TRUE);
    }

    // If the profile does not exist, forbid access to this instance.
    return AccessResult::forbidden('Invalid profile on display_builder_instance.');
  }

  /**
   * Checks access based on instance prefix/type.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The entity for which to check access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function checkBuildableAccess(InstanceInterface $instance, AccountInterface $account): AccessResultInterface {
    $instance_id = (string) $instance->id();
    $providers = $this->displayBuildableManager->getDefinitions();

    foreach ($providers as $provider) {
      if (\str_starts_with($instance_id, $provider['instance_prefix'])) {
        return $provider['class']::checkAccess($instance_id, $account);
      }
    }

    // If an instance is not managed by a provider (for example: a demo or a
    // test), we allow.
    return AccessResult::allowed();
  }

}
