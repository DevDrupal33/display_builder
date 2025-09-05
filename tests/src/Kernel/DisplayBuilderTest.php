<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the DisplayBuilder class.
 *
 * @internal
 */
#[CoversClass('\Drupal\display_builder\Entity\DisplayBuilder')]
#[Group('display_builder')]
final class DisplayBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
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
    $this->installEntitySchema('display_builder');
  }

  /**
   * Tests creating and editing a Display Builder entity.
   */
  public function testDisplayBuilderEntityCrud(): void {
    $enable_island = ['enable' => TRUE, 'weight' => 2];
    $updated_island = ['enable' => FALSE, 'weight' => 3];

    // Create a new display builder entity.
    $displayBuilder = DisplayBuilder::create([
      'id' => 'test_builder',
      'label' => 'Test Builder',
      'description' => 'Test Description',
      'library' => 'cdn',
      'debug' => FALSE,
      'islands' => [
        'test_island_button' => $enable_island,
        'test_island_contextual' => $enable_island,
        'test_island_library' => $enable_island,
        'test_island_menu' => $enable_island,
      ],
    ]);
    $displayBuilder->save();

    // Test that the entity was created correctly.
    self::assertNotEmpty($displayBuilder->id());
    self::assertSame('test_builder', $displayBuilder->id());
    self::assertSame('Test Builder', $displayBuilder->label());
    self::assertSame('Test Description', $displayBuilder->get('description'));
    self::assertSame('cdn', $displayBuilder->get('library'));
    self::assertFalse($displayBuilder->get('debug'));

    // Test islands enabled.
    $config = $displayBuilder->getIslandConfigurations();
    self::assertSame($enable_island, $config['test_island_button']);
    self::assertSame($enable_island, $config['test_island_contextual']);
    self::assertSame($enable_island, $config['test_island_library']);
    self::assertSame($enable_island, $config['test_island_menu']);

    // Test entity loading.
    $loaded = DisplayBuilder::load('test_builder');
    self::assertNotNull($loaded);
    self::assertSame($displayBuilder->id(), $loaded->id());
    self::assertSame($displayBuilder->label(), $loaded->label());

    // Update the entity.
    $displayBuilder->set('label', 'Updated Builder');
    $displayBuilder->set('description', 'Updated Description');
    $displayBuilder->set('library', 'local');
    $displayBuilder->set('debug', TRUE);
    $displayBuilder->set('islands', [
      'test_island_button' => $updated_island,
      'test_island_contextual' => $updated_island,
      'test_island_library' => $updated_island,
      'test_island_menu' => $updated_island,
    ]);
    $displayBuilder->save();

    // Reload and verify changes.
    $updated = DisplayBuilder::load('test_builder');
    self::assertSame('Updated Builder', $updated->label());
    self::assertSame('Updated Description', $updated->get('description'));
    self::assertSame('local', $updated->get('library'));
    self::assertTrue($updated->get('debug'));

    // Test islands enabled.
    $config = $displayBuilder->getIslandConfigurations();
    self::assertSame($updated_island, $config['test_island_button']);
    self::assertSame($updated_island, $config['test_island_contextual']);
    self::assertSame($updated_island, $config['test_island_library']);
    self::assertSame($updated_island, $config['test_island_menu']);
  }

  /**
   * Tests islands management.
   */
  public function testIslandConfiguration(): void {
    $islandId = 'test_island_view';

    // Create a display builder with island configuration.
    $displayBuilder = DisplayBuilder::create([
      'id' => 'test_islands',
      'label' => 'Test Islands',
      'description' => 'Test Description',
      'islands' => [
        $islandId => [
          'enable' => TRUE,
          'weight' => 0,
          'region' => 'main',
          'string_value' => 'test value',
          'bool_value' => 1,
          'string_array' => ['foo' => 'value1', 'bar' => 'value2'],
        ],
      ],
    ]);
    $displayBuilder->save();

    // Test getting island configuration.
    $islandConfig = $displayBuilder->getIslandConfiguration($islandId);
    self::assertNotEmpty($islandConfig);
    self::assertTrue($islandConfig['enable']);
    self::assertSame(0, $islandConfig['weight']);
    self::assertSame('main', $islandConfig['region']);

    // Test custom configuration values.
    self::assertSame('test value', $islandConfig['string_value'], 'String value is correctly stored');
    self::assertSame(1, $islandConfig['bool_value'], 'Boolean value is correctly stored');
    self::assertSame(['foo' => 'value1', 'bar' => 'value2'], $islandConfig['string_array'], 'Array of strings is correctly stored');

    // Test setting new island configuration with updated custom values.
    $newConfig = [
      'enable' => FALSE,
      'weight' => 10,
      'region' => 'sidebar',
      'string_value' => 'updated value',
      'bool_value' => 0,
      'string_array' => ['foo' => 'new1', 'bar' => 'new2'],
    ];
    $displayBuilder->setIslandConfiguration($islandId, $newConfig);
    $displayBuilder->save();

    // Reload and verify island configuration changes.
    $updated = DisplayBuilder::load('test_islands');
    $updatedConfig = $updated->getIslandConfiguration($islandId);
    self::assertFalse($updatedConfig['enable']);
    self::assertSame(10, $updatedConfig['weight']);
    self::assertSame('sidebar', $updatedConfig['region']);
    // Verify updated configuration including custom values.
    $updated = DisplayBuilder::load('test_islands');
    $updatedConfig = $updated->getIslandConfiguration($islandId);
    self::assertFalse($updatedConfig['enable']);
    self::assertSame(10, $updatedConfig['weight']);
    self::assertSame('sidebar', $updatedConfig['region']);

    // Verify updated custom configuration values.
    self::assertSame('updated value', $updatedConfig['string_value'], 'Updated string value is correctly stored');
    self::assertSame(0, $updatedConfig['bool_value'], 'Updated boolean value is correctly stored');
    self::assertSame(['foo' => 'new1', 'bar' => 'new2'], $updatedConfig['string_array'], 'Updated array of strings is correctly stored');

    // Test getting all island configurations.
    $allConfigs = $updated->getIslandConfigurations();
    self::assertArrayHasKey($islandId, $allConfigs);
    self::assertSame($updatedConfig, $allConfigs[$islandId]);

    // Test getting enabled islands.
    $enabledIslands = $updated->getIslandEnabled();
    self::assertEmpty($enabledIslands);

    // Enable the island and test again.
    $displayBuilder->setIslandConfiguration($islandId, ['enable' => TRUE] + $newConfig);
    $displayBuilder->save();
    $updated = DisplayBuilder::load('test_islands');
    $enabledIslands = $updated->getIslandEnabled();
    self::assertArrayHasKey($islandId, $enabledIslands);
  }

  /**
   * Test the getRoles() method.
   */
  public function testGetRoles(): void {
    $displayBuilder = DisplayBuilder::create([
      'id' => 'role_test',
      'label' => 'Role Test',
      'description' => 'Test Description',
    ]);
    $displayBuilder->save();

    // Create a role with the permission.
    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test Role',
      'permissions' => ['use display builder role_test'],
    ]);
    $role->save();

    $roles = $displayBuilder->getRoles();
    self::assertContains('Test Role', $roles);
  }

  /**
   * Test the toUrl() method.
   */
  public function testToUrlEditPluginForm(): void {
    $displayBuilder = DisplayBuilder::create([
      'id' => 'url_test',
      'label' => 'URL Test',
      'description' => 'Test Description',
    ]);
    $displayBuilder->save();

    $url = $displayBuilder->toUrl('edit-plugin-form', ['island_id' => 'foo']);
    self::assertSame('/admin/structure/display-builder/url_test/edit/foo', $url->toString());
  }

  /**
   * Test the library and debug mode.
   */
  public function testGetLibraryAndDebug(): void {
    $displayBuilder = DisplayBuilder::create([
      'id' => 'lib_test',
      'label' => 'Lib Test',
      'description' => 'Test Description',
      'library' => 'cdn',
      'debug' => TRUE,
    ]);
    self::assertSame('cdn', $displayBuilder->getLibrary());
    self::assertTrue($displayBuilder->isDebugModeActivated());
  }

}
