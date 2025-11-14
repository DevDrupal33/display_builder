<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\ConfigFormBuilder;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the ConfigFormBuilder class.
 *
 * @internal
 */
#[CoversClass(ConfigFormBuilder::class)]
#[Group('display_builder')]
final class ConfigFormBuilderTest extends DisplayBuilderKernelTestBase {

  use UserCreationTrait;

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
   * The access control handler for Profiles.
   */
  private ConfigFormBuilderInterface $configFormBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
    $this->configFormBuilder = $this->container->get('display_builder.config_form_builder');
  }

  /**
   * Test the ConfigFormBuilderTest::build() method.
   *
   * @param array<string, mixed> $data
   *   The data setup for the test.
   * @param array<string, mixed> $expect
   *   The expected results.
   */
  #[DataProvider('configFormBuilderProvider')]
  public function testConfigFormBuilderBuild(array $data, array $expect): void {
    foreach ($data['profiles'] as $pid) {
      self::createDisplayBuilderProfile($pid);
    }

    // Create the user with the permissions to test.
    $permissions = [];

    if (isset($data['user_has_profile_id_permission'])) {
      $permissions[] = \sprintf($this->useDisplayBuilderPermission, $data['user_has_profile_id_permission']);
    }
    $this->setUpCurrentUser([], $permissions ?? []);

    // Create the buildable.
    $buildable = $this->createDisplayBuilderBuildable($data['buildable_use_profile_id']);

    // Create the config form for tests.
    $actual = $this->configFormBuilder->build($buildable, FALSE);

    self::assertArrayHasKey(ConfigFormBuilderInterface::PROFILE_PROPERTY, $actual, 'Profile select is built.');
    $data = $actual[ConfigFormBuilderInterface::PROFILE_PROPERTY];

    if (!empty($expect['has_options'])) {
      self::assertArrayHasKey('#options', $data, 'Options exist.');

      if (!empty($expect['option_contains'])) {
        self::assertArrayHasKey($expect['option_contains'], $data['#options'], 'Expected profile present in options.');
      }
    }
    else {
      self::assertArrayNotHasKey('#options', $data, 'No options are set.');
    }

    if (!empty($expect['has_markup'])) {
      self::assertArrayHasKey('#markup', $data, 'A message is displayed to the user as no access is granted.');
    }
    else {
      self::assertArrayNotHasKey('#markup', $data, 'No message markup expected.');
    }

    if (!empty($expect['has_default'])) {
      self::assertArrayHasKey('#default_value', $data, 'Default value is set.');

      if (isset($expect['default_value'])) {
        self::assertEquals($expect['default_value'], $data['#default_value'], 'Default value matches expected.');
      }
    }
    else {
      self::assertArrayNotHasKey('#default_value', $data, 'No default value when none is set.');
    }

    if (!empty($expect['disabled'])) {
      self::assertTrue($data['#disabled'], 'The select is disabled.');
    }

    if (!empty($expect['description'])) {
      self::assertEquals($expect['description'], (string) $data['#description'], 'Disabled description matches expected.');
    }

    // Test the mandatory flag, default is FALSE, build again for TRUE.
    self::assertArrayNotHasKey('#required', $data, 'No required when not mandatory or not built.');

    if (isset($data['can_required']) && $data['can_required'] === TRUE) {
      $actual = $this->configFormBuilder->build($buildable, TRUE);
      self::assertArrayHasKey('#required', $actual[ConfigFormBuilderInterface::PROFILE_PROPERTY], 'Required when mandatory if built.');
    }
  }

  /**
   * Data provider for testConfigFormBuilderBuild().
   *
   * Each case needs:
   * - profiles: Display Builder profiles to create (array of ids)
   * - user_has_profile_id_permission: Add profile permission on user (or NULL)
   * - buildable_use_profile_id: Add profile to the buildable (or NULL)
   * - expect: expected assertions for the built form.
   *
   * @return array<string, array<string, mixed>>
   *   The data to test and expected.
   */
  public static function configFormBuilderProvider(): array {
    return [
      'user without permission' => [
        'data' => [
          'profiles' => [],
          'user_has_profile_id_permission' => NULL,
          'buildable_use_profile_id' => NULL,
        ],
        'expect' => [
          'has_options' => FALSE,
          'has_markup' => TRUE,
        ],
      ],
      'user with profile but not buildable profile' => [
        'data' => [
          'profiles' => ['test_user_profile'],
          'user_has_profile_id_permission' => 'test_user_profile',
          'buildable_use_profile_id' => NULL,
        ],
        'expect' => [
          'has_options' => TRUE,
          'option_contains' => 'test_user_profile',
          'has_default' => FALSE,
        ],
      ],
      'user with profile but not buildable profile' => [
        'data' => [
          'profiles' => ['test_user_profile'],
          'user_has_profile_id_permission' => 'test_user_profile',
          'buildable_use_profile_id' => NULL,
          'can_required' => TRUE,
        ],
        'expect' => [
          'has_options' => TRUE,
          'option_contains' => 'test_user_profile',
          'has_default' => FALSE,
        ],
      ],
      'user with profile same as buildable' => [
        'data' => [
          'profiles' => ['test_user_instance_profile'],
          'user_has_profile_id_permission' => 'test_user_instance_profile',
          'buildable_use_profile_id' => 'test_user_instance_profile',
          'can_required' => TRUE,
        ],
        'expect' => [
          'has_options' => TRUE,
          'option_contains' => 'test_user_instance_profile',
          'has_default' => TRUE,
          'default_value' => 'test_user_instance_profile',
        ],
      ],
      'user with profile different from buildable profile' => [
        'data' => [
          'profiles' => ['test_user_profile', 'test_buildable_use_profile_id'],
          'user_has_profile_id_permission' => 'test_user_profile',
          'buildable_use_profile_id' => 'test_buildable_use_profile_id',
        ],
        'expect' => [
          'has_options' => TRUE,
          'option_contains' => 'test_buildable_use_profile_id',
          'disabled' => TRUE,
          'description' => 'You are not allowed to use Display Builder here.',
        ],
      ],
    ];
  }

}
