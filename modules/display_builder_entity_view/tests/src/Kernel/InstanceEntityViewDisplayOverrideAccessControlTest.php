<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\display_builder\InstanceAccessControlHandler;
use Drupal\Tests\display_builder\Kernel\AccessControlTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the instance access control handlers.
 *
 * @internal
 */
#[CoversClass(InstanceAccessControlHandler::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class InstanceEntityViewDisplayOverrideAccessControlTest extends AccessControlTestBase {

  /**
   * The instance ID prefix of the buildable under test.
   *
   * @see \Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride
   */
  private const PREFIX = 'entity_override__';

  /**
   * {@inheritdoc}
   */
  public string $adminPermission = 'administer users';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    // Required for administer users permission.
    'field',
    // Required for administer users permission.
    'field_ui',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_ui',
    'display_builder_entity_view',
  ];

  /**
   * Test the ::access() method.
   *
   * @param array<string, mixed> $data
   *   The data setup for the test.
   * @param array<string, mixed> $expect
   *   The expected results.
   */
  #[DataProvider('accessInstanceEntityViewDisplayProvider')]
  public function testInstanceEntityViewDisplayAccess(array $data, array $expect): void {
    // Create the Display builder profiles and verify permissions exist.
    foreach ($data['profiles'] as $pid) {
      $permission = \sprintf($this->useDisplayBuilderPermission, $pid);
      self::createDisplayBuilderProfile($pid);
      $availablePermissions = $this->userPermissions->getPermissions();
      self::assertArrayHasKey($permission, $availablePermissions, 'Display builder profile permission is created and available.');
    }

    // Create the user with the permissions to test.
    $permissions = [];

    if ($data['user_has_admin_permission']) {
      $permissions[] = $this->adminPermission;
    }

    if (isset($data['user_has_profile_id_permission'])) {
      $permissions[] = \sprintf($this->useDisplayBuilderPermission, $data['user_has_profile_id_permission']);
    }
    $user = $this->setUpCurrentUser([], $permissions ?? []);

    // Create the instance with profile and instance id.
    $instance = $this->createDisplayBuilderInstance($data['instance_use_profile_id'] ?? NULL, self::PREFIX . 'user__1__field_test');

    // Test the expected results.
    if ($expect['view']) {
      self::assertTrue($this->accessControl->access($instance, 'view', $user), 'User can view the instance.');
    }
    else {
      self::assertFalse($this->accessControl->access($instance, 'view', $user), 'User can not view the instance.');
    }

    if ($expect['update']) {
      self::assertTrue($this->accessControl->access($instance, 'update', $user), 'User can update the instance.');
    }
    else {
      self::assertFalse($this->accessControl->access($instance, 'update', $user), 'User can not update the instance.');
    }

    if ($expect['delete']) {
      self::assertTrue($this->accessControl->access($instance, 'delete', $user), 'User can delete the instance.');
    }
    else {
      self::assertFalse($this->accessControl->access($instance, 'delete', $user), 'User can not delete the instance.');
    }
  }

  /**
   * Data provider for testInstanceEntityViewDisplayAccess().
   *
   * Each case needs:
   * - profiles: Display Builder array of profiles to create
   * - user_has_admin_permission: Add user admin permission
   * - user_has_profile_id_permission: Add profile id permission on user
   * - instance_use_profile_id: Add profile id permission on user
   * - expect: expected use access results for view, update, delete.
   *
   * @return array<string, array<string, mixed>>
   *   The data to test and expected.
   */
  public static function accessInstanceEntityViewDisplayProvider(): array {
    return [
      'user without permission' => [
        'data' => [
          'profiles' => ['instance_only'],
          'user_has_admin_permission' => FALSE,
          'instance_use_profile_id' => 'instance_only',
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with same profile as instance but not admin views' => [
        'data' => [
          'profiles' => ['user_instance'],
          'user_has_admin_permission' => FALSE,
          'user_has_profile_id_permission' => 'user_instance',
          'instance_use_profile_id' => 'user_instance',
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with different profile than instance' => [
        'data' => [
          'profiles' => ['user_profile', 'instance_profile'],
          'user_has_admin_permission' => FALSE,
          'user_has_profile_id_permission' => 'user_profile',
          'instance_use_profile_id' => 'instance_profile',
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with admin permission but not profile' => [
        'data' => [
          'profiles' => [],
          'user_has_admin_permission' => TRUE,
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with admin and profile permission' => [
        'data' => [
          'profiles' => ['test_profile'],
          'user_has_admin_permission' => TRUE,
          'user_has_profile_id_permission' => 'test_profile',
          'instance_use_profile_id' => 'test_profile',
        ],
        'expect' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
      'user with admin and other profile permission' => [
        'data' => [
          'profiles' => ['user_profile', 'instance_profile'],
          'user_has_admin_permission' => TRUE,
          'user_has_profile_id_permission' => 'user_profile',
          'instance_use_profile_id' => 'instance_profile',
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
    ];
  }

}
