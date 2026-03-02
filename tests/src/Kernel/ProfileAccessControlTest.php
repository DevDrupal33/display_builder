<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\ProfileAccessControlHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the profile access control handlers.
 *
 * @internal
 */
#[CoversClass(ProfileAccessControlHandler::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ProfileAccessControlTest extends AccessControlTestBase {

  /**
   * {@inheritdoc}
   */
  public string $accessControlHandler = 'display_builder_profile';

  /**
   * Test the ::access() method.
   *
   * @param array<string, mixed> $data
   *   The data setup for the test.
   * @param array<string, mixed> $expect
   *   The expected results.
   */
  #[DataProvider('accessProfileProvider')]
  public function testProfileAccess(array $data, array $expect): void {
    // Create the Display builder profiles and verify permissions exist.
    $profiles = [];

    foreach ($data['profiles'] as $pid) {
      $permission = \sprintf($this->useDisplayBuilderPermission, $pid);
      $profiles[$pid] = self::createDisplayBuilderProfile($pid);
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

    // Test the expected results.
    if (isset($data['user_test_profile_id'])) {
      $profile = $profiles[$data['user_test_profile_id']];
    }
    else {
      $profile = $profiles[$data['user_has_profile_id_permission'] ?? $data['profiles'][0]];
    }

    if ($expect['view']) {
      self::assertTrue($this->accessControl->access($profile, 'view', $user), 'User can view the profile.');
    }
    else {
      self::assertFalse($this->accessControl->access($profile, 'view', $user), 'User can not view the profile.');
    }

    if ($expect['update']) {
      self::assertTrue($this->accessControl->access($profile, 'update', $user), 'User can update the profile.');
    }
    else {
      self::assertFalse($this->accessControl->access($profile, 'update', $user), 'User can not update the profile.');
    }

    if ($expect['delete']) {
      self::assertTrue($this->accessControl->access($profile, 'delete', $user), 'User can delete the profile.');
    }
    else {
      self::assertFalse($this->accessControl->access($profile, 'delete', $user), 'User can not delete the profile.');
    }
  }

  /**
   * Data provider for testProfileAccess().
   *
   * Each case needs:
   * - profile_id: Display Builder profile to create
   * - user_has_admin_permission: Add user admin permission
   * - user_has_profile_id_permission: Add profile id permission on user
   * - user_test_profile_id: Profile to test access against, default to
   *   user_has_profile_id_permission
   * - expect: expected use access results for view, update, delete.
   *
   * @return array<string, array<string, mixed>>
   *   The data to test and expected.
   */
  public static function accessProfileProvider(): array {
    return [
      'user without permission' => [
        'data' => [
          'profiles' => ['test_profile'],
          'user_has_admin_permission' => FALSE,
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with profile permission' => [
        'data' => [
          'profiles' => ['test_user_profile'],
          'user_has_admin_permission' => FALSE,
          'user_has_profile_id_permission' => 'test_user_profile',
        ],
        'expect' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with other profile permission' => [
        'data' => [
          'profiles' => ['test_user_profile', 'test_other_profile'],
          'user_has_admin_permission' => FALSE,
          'user_has_profile_id_permission' => 'test_user_profile',
          'user_test_profile_id' => 'test_other_profile',
        ],
        'expect' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      'user with admin permission' => [
        'data' => [
          'profiles' => ['test_admin_profile'],
          'user_has_admin_permission' => TRUE,
        ],
        'expect' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
      'user with profile and admin permission' => [
        'data' => [
          'profiles' => ['test_user_admin_profile'],
          'user_has_admin_permission' => TRUE,
          'user_has_profile_id_permission' => 'test_user_admin_profile',
        ],
        'expect' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
    ];
  }

}
