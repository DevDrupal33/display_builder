<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\PermissionHandlerInterface;

/**
 * Base class to test access control handlers.
 *
 * @internal
 */
abstract class AccessControlTestBase extends DisplayBuilderKernelTestBase {

  use UserCreationTrait;

  /**
   * The admin Display Builder profiles permission.
   */
  public string $adminPermission = 'administer display builder profile';

  /**
   * The access control handler name.
   */
  public string $accessControlHandler = 'display_builder_instance';

  /**
   * The access control handler for Instances.
   */
  protected EntityAccessControlHandlerInterface $accessControl;

  /**
   * The user permissions service.
   */
  protected PermissionHandlerInterface $userPermissions;

  /**
   * The use Display Builder profile permission pattern for sprintf.
   */
  protected string $useDisplayBuilderPermission = 'use display builder %s';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'display_builder',
    'display_builder_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
    $this->userPermissions = $this->container->get('user.permissions');

    $this->accessControl = $this->container->get('entity_type.manager')->getAccessControlHandler($this->accessControlHandler);
  }

}
