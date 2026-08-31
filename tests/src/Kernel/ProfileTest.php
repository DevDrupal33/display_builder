<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Profile;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the DisplayBuilder class.
 *
 * @internal
 */
#[CoversClass(Profile::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ProfileTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
  }

  /**
   * Tests creating and editing a Display Builder entity.
   */
  public function testDisplayBuilderEntityCrud(): void {
    $profile_test_id = 'test_profile_id';

    $enable_island = ['status' => TRUE, 'weight' => 2];
    $updated_island = ['status' => FALSE, 'weight' => 3];

    // Create a new display builder entity.
    $data = [
      'label' => 'Test builder',
      'description' => 'Test Description crud',
      'islands' => [
        'test_island_button' => $enable_island,
        'test_island_contextual' => $enable_island,
        'test_island_library' => $enable_island,
        'test_island_menu' => $enable_island,
      ],
    ];
    $profile = self::createDisplayBuilderProfile($profile_test_id, $data);

    // Test that the entity was created correctly.
    self::assertNotEmpty($profile->id());
    self::assertSame($profile_test_id, $profile->id());
    self::assertSame($data['label'], $profile->label());
    self::assertSame($data['description'], $profile->get('description'));

    // Test islands enabled.
    $config = $profile->getIslandConfigurations();
    self::assertSame($enable_island, $config['test_island_button']);
    self::assertSame($enable_island, $config['test_island_contextual']);
    self::assertSame($enable_island, $config['test_island_library']);
    self::assertSame($enable_island, $config['test_island_menu']);

    // Test entity loading.
    $loaded = Profile::load($profile_test_id);
    self::assertNotNull($loaded);
    self::assertSame($profile->id(), $loaded->id());
    self::assertSame($profile->label(), $loaded->label());

    // Update the entity.
    $profile->set('label', 'Updated Builder');
    $profile->set('description', 'Updated Description');
    $profile->set('islands', [
      'test_island_button' => $updated_island,
      'test_island_contextual' => $updated_island,
      'test_island_library' => $updated_island,
      'test_island_menu' => $updated_island,
    ]);
    $profile->save();

    // Reload and verify changes.
    $updated = Profile::load($profile_test_id);
    self::assertSame('Updated Builder', $updated->label());
    self::assertSame('Updated Description', $updated->get('description'));

    // Test islands enabled.
    $config = $profile->getIslandConfigurations();
    self::assertSame($updated_island, $config['test_island_button']);
    self::assertSame($updated_island, $config['test_island_contextual']);
    self::assertSame($updated_island, $config['test_island_library']);
    self::assertSame($updated_island, $config['test_island_menu']);
  }

  /**
   * Test the ::getRoles() method.
   */
  public function testGetRoles(): void {
    $profile = self::createDisplayBuilderProfile('role_test');

    // Create a role with the permission.
    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test Role',
      'permissions' => ['use display builder role_test'],
    ]);
    $role->save();

    $roles = $profile->getRoles();
    self::assertContains('Test Role', $roles);
  }

  /**
   * Tests the ::getIslandConfiguration() and ::setIslandConfiguration().
   */
  public function testIslandConfiguration(): void {
    $islandId = 'test_island_view';
    $data = [];

    // Create a display builder with island configuration.
    $data['islands'] = [
      $islandId => [
        'status' => TRUE,
        'weight' => 0,
        'string_value' => 'test value',
        'bool_value' => 1,
        'string_array' => ['foo' => 'value1', 'bar' => 'value2'],
      ],
    ];
    $profile = self::createDisplayBuilderProfile('test_islands', $data);

    // Test getting island configuration.
    $islandConfig = $profile->getIslandConfiguration($islandId);
    self::assertNotEmpty($islandConfig);
    self::assertTrue($islandConfig['status']);
    self::assertSame(0, $islandConfig['weight']);

    // Test custom configuration values.
    self::assertSame('test value', $islandConfig['string_value'], 'String value is correctly stored');
    self::assertSame(1, $islandConfig['bool_value'], 'Boolean value is correctly stored');
    self::assertSame(['foo' => 'value1', 'bar' => 'value2'], $islandConfig['string_array'], 'Array of strings is correctly stored');

    // Test setting new island configuration with updated custom values.
    $newConfig = [
      'status' => FALSE,
      'weight' => 10,
      'string_value' => 'updated value',
      'bool_value' => 0,
      'string_array' => ['foo' => 'new1', 'bar' => 'new2'],
    ];
    $profile->setIslandConfiguration($islandId, $newConfig);
    $profile->save();

    // Reload and verify island configuration changes.
    $updated = Profile::load('test_islands');
    $updatedConfig = $updated->getIslandConfiguration($islandId);
    self::assertFalse($updatedConfig['status']);
    self::assertSame(10, $updatedConfig['weight']);

    // Verify updated configuration including custom values.
    $updated = Profile::load('test_islands');
    $updatedConfig = $updated->getIslandConfiguration($islandId);
    self::assertFalse($updatedConfig['status']);
    self::assertSame(10, $updatedConfig['weight']);

    // Verify updated custom configuration values.
    self::assertSame('updated value', $updatedConfig['string_value'], 'Updated string value is correctly stored');
    self::assertSame(0, $updatedConfig['bool_value'], 'Updated boolean value is correctly stored');
    self::assertSame(['foo' => 'new1', 'bar' => 'new2'], $updatedConfig['string_array'], 'Updated array of strings is correctly stored');

    // Test getting all island configurations.
    $allConfigs = $updated->getIslandConfigurations();
    self::assertArrayHasKey($islandId, $allConfigs);
    self::assertSame($updatedConfig, $allConfigs[$islandId]);

    // Test getting enabled islands.
    $enabledIslands = $updated->getEnabledIslands();
    self::assertEmpty($enabledIslands);

    // Enable the island and test again.
    $profile->setIslandConfiguration($islandId, ['status' => TRUE] + $newConfig);
    $profile->save();
    $updated = Profile::load('test_islands');
    $enabledIslands = $updated->getEnabledIslands();
    self::assertArrayHasKey($islandId, $enabledIslands);
  }

  /**
   * Migrates legacy profile island config from layers to scaffold.
   */
  public function testLayersIslandMigratesToScaffold(): void {
    $profile_storage = $this->container->get('entity_type.manager')->getStorage('display_builder_profile');

    $profile = Profile::create([
      'id' => 'legacy_layers_profile',
      'label' => 'Legacy layers profile',
      'description' => 'Test migration',
      'islands' => [
        'layers' => [
          'status' => TRUE,
          'weight' => -4,
        ],
        'builder' => [
          'status' => TRUE,
          'weight' => -5,
        ],
      ],
    ]);
    $profile->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('display_builder');

    require_once \Drupal::root() . '/' . $module_path . '/display_builder.post_update.php';
    \display_builder_post_update_2();

    $profile = $profile_storage->load('legacy_layers_profile');
    self::assertNotNull($profile);

    $islands = $profile->getIslandConfigurations();
    self::assertArrayHasKey('scaffold', $islands);
    self::assertSame([
      'status' => TRUE,
      'weight' => -4,
    ], $islands['scaffold']);
    self::assertArrayNotHasKey('layers', $islands);
    self::assertSame([
      'status' => TRUE,
      'weight' => -5,
    ], $islands['builder']);
  }

  /**
   * Test the ::toUrl() method.
   */
  public function testToUrlEditPluginForm(): void {
    $profile = self::createDisplayBuilderProfile('url_test');

    $url = $profile->toUrl('edit-plugin-form', ['island_id' => 'foo']);
    self::assertSame('/admin/structure/display-builder/url_test/edit/foo', $url->toString());
  }

}
