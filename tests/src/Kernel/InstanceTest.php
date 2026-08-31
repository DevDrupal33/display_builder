<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Instance entity.
 *
 * @internal
 */
#[CoversClass(Instance::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class InstanceTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_ui',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder', 'display_builder_test']);
  }

  /**
   * Test the ::label() method.
   */
  public function testLabel(): void {
    $instance = $this->createDisplayBuilderInstance(NULL, 'foo__test_instance');
    self::assertSame('Test instance', $instance->label());
    $instance = $this->createDisplayBuilderInstance(NULL, 'no_provider');
    self::assertSame('no_provider', $instance->label());
  }

  /**
   * Test ::label() when the buildable cannot be built.
   *
   * An instance outlives the display it was built from, so a listing page must
   * still render a row for it.
   */
  public function testLabelWithoutBuildable(): void {
    $instance = Instance::create([
      'id' => 'gone__my_display',
      'buildable' => [
        'plugin_id' => 'no_such_plugin',
        'configuration' => [],
      ],
    ]);

    self::assertSame('My display', $instance->label());
  }

  /**
   * Test the ::previewWithChrome() method delegates to the buildable plugin.
   */
  public function testPreviewWithChrome(): void {
    $instance = $this->createDisplayBuilderInstance(NULL, 'foo__test_instance');

    self::assertTrue($instance->previewWithChrome());
  }

  /**
   * Test ::previewWithChrome() when the buildable cannot be built.
   *
   * An instance outlives the display it was built from. With no plugin to
   * ask, it is treated like any other fragment: wrapped in chrome, not bare.
   */
  public function testPreviewWithChromeWithoutBuildable(): void {
    $instance = Instance::create([
      'id' => 'gone__my_display',
      'buildable' => [
        'plugin_id' => 'no_such_plugin',
        'configuration' => [],
      ],
    ]);

    self::assertTrue($instance->previewWithChrome());
  }

  /**
   * Test the ::getProfile() methods.
   */
  public function testProfile(): void {
    $this->createDisplayBuilderProfile('test_profile');
    $instance = $this->createDisplayBuilderInstance('test_profile');

    $loaded_profile = $instance->getProfile();
    self::assertInstanceOf(ProfileInterface::class, $loaded_profile);
    self::assertSame('test_profile', $loaded_profile->id());
  }

  /**
   * Test attachToRoot.
   */
  public function testAttachToRoot(): void {
    $instance = $this->createDisplayBuilderInstance();
    $node_id1 = $instance->attachToRoot(0, 'test_group_source', ['value' => '1']);
    self::assertNotEmpty($node_id1);

    $state = $instance->getCurrentState();
    self::assertCount(1, $state);
    self::assertSame($node_id1, $state[0]['node_id']);
    self::assertSame('test_group_source', $state[0]['source_id']);
    self::assertSame(['value' => '1'], $state[0]['source']);

    $node_id2 = $instance->attachToRoot(1, 'test_group_source', ['value' => '2']);
    self::assertCount(2, $instance->getCurrentState());
    self::assertSame($node_id2, $instance->getCurrentState()[1]['node_id']);
  }

  /**
   * Test attachToSlot.
   */
  public function testAttachToSlot(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = [
      'component' => [
        'component_id' => 'display_builder_test:test_1',
      ],
    ];
    $node_id_parent = $instance->attachToRoot(0, 'component', $comp_data);
    $node_id_child = $instance->attachToSlot($node_id_parent, 'slot_1', 0, 'test_group_source', ['value' => 'child']);

    self::assertNotEmpty($node_id_child);
    self::assertSame($node_id_parent, $instance->getParentId($node_id_child));

    $parent_node = $instance->getNode($node_id_parent);
    self::assertSame($node_id_child, $parent_node['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
  }

  /**
   * Test moveToSlot.
   */
  public function testMoveToSlot(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = [
      'component' => [
        'component_id' => 'display_builder_test:test_1',
      ],
    ];
    $node_id_parent = $instance->attachToRoot(0, 'component', $comp_data);
    $node_id_to_move = $instance->attachToRoot(1, 'test_group_source', ['value' => 'to_move']);

    // Move to slot.
    $instance->moveToSlot($node_id_to_move, $node_id_parent, 'slot_1', 0);

    self::assertSame($node_id_parent, $instance->getParentId($node_id_to_move));
    $parent_node = $instance->getNode($node_id_parent);
    self::assertCount(1, $parent_node['source']['component']['slots']['slot_1']['sources']);
    self::assertSame($node_id_to_move, $parent_node['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
  }

  /**
   * Test moveToRoot.
   */
  public function testMoveToRoot(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = [
      'component' => [
        'component_id' => 'display_builder_test:test_1',
      ],
    ];
    $node_id_parent = $instance->attachToRoot(0, 'component', $comp_data);
    $node_id_child = $instance->attachToSlot($node_id_parent, 'slot_1', 0, 'test_group_source', ['value' => 'child']);

    // Move child to root.
    $instance->moveToRoot($node_id_child, 1);

    self::assertNull($instance->getParentId($node_id_child));
    $state = $instance->getCurrentState();
    self::assertCount(2, $state);
    self::assertSame($node_id_child, $state[1]['node_id']);
  }

  /**
   * Test node management: getNode, setSource, setThirdPartySettings, remove.
   */
  public function testNodeManagement(): void {
    $instance = $this->createDisplayBuilderInstance();
    $node_id = $instance->attachToRoot(0, 'test_group_source', ['value' => 'initial']);

    $node = $instance->getNode($node_id);
    self::assertSame($node_id, $node['node_id']);

    $instance->setSource($node_id, 'test_group_source', ['value' => 'updated']);
    $node = $instance->getNode($node_id);
    self::assertSame(['value' => 'updated'], $node['source']);

    $instance->setThirdPartySettings($node_id, 'test_island', ['foo' => 'bar']);
    $node = $instance->getNode($node_id);
    self::assertSame(['foo' => 'bar'], $node['third_party_settings']['test_island']);

    $instance->remove($node_id);
    $node = $instance->getNode($node_id);
    self::assertEmpty($node);
  }

  /**
   * Test setSource exception when node ID mismatch.
   */
  public function testSetSourceException(): void {
    $instance = $this->createDisplayBuilderInstance();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Internal node ID mismatch');
    $instance->setSource('non_existent_id', 'test_group_source', []);
  }

  /**
   * Test remove on non-existent node is a silent no-op.
   */
  public function testRemoveSilentOnNonExistentNode(): void {
    $instance = $this->createDisplayBuilderInstance();
    $node_id = $instance->attachToRoot(0, 'test_group_source', ['value' => 'keep']);

    // Removing a non-existent node must not alter state.
    $instance->remove('non_existent_id');
    self::assertCount(1, $instance->getCurrentState());
    self::assertSame($node_id, $instance->getCurrentState()[0]['node_id']);
  }

  /**
   * Test that publish() persists the published timestamp.
   */
  public function testPublishPersistsTimestamp(): void {
    $instance = $this->createDisplayBuilderInstance();
    $instance->attachToRoot(0, 'test_group_source', ['value' => 'foo']);

    $instance->publish();

    $reloaded = $this->loadInstance($instance->id());
    self::assertNotNull($reloaded->getPublishedTime());
  }

  /**
   * Test getParentId returns the correct value for root, child, and missing.
   */
  public function testGetParentId(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = ['component' => ['component_id' => 'display_builder_test:test_1']];
    $node_root = $instance->attachToRoot(0, 'component', $comp_data);
    $node_child = $instance->attachToSlot($node_root, 'slot_1', 0, 'test_group_source', ['value' => 'child']);

    self::assertNull($instance->getParentId($node_root));
    self::assertSame($node_root, $instance->getParentId($node_child));
    self::assertNull($instance->getParentId('non_existent_id'));
  }

  /**
   * Test attachToSlot throws when parent does not exist.
   */
  public function testAttachToSlotThrowsOnInvalidParent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Parent or slot not found');
    $instance->attachToSlot('non_existent_parent', 'slot_1', 0, 'test_group_source', []);
  }

  /**
   * Test setThirdPartySettings on a non-existent node is a silent no-op.
   */
  public function testSetThirdPartySettingsOnNonExistentNodeIsSilent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $node_id = $instance->attachToRoot(0, 'test_group_source', ['value' => 'keep']);
    $before = $instance->getCurrentState();

    // Must not throw and must not alter state.
    $instance->setThirdPartySettings('non_existent_id', 'some_island', ['data' => 'x']);
    self::assertSame($before, $instance->getCurrentState());
    self::assertSame($node_id, $instance->getCurrentState()[0]['node_id']);
  }

  /**
   * Test getPathIndex at the Instance level.
   */
  public function testGetPathIndex(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = ['component' => ['component_id' => 'display_builder_test:test_1']];
    $node_a = $instance->attachToRoot(0, 'component', $comp_data);
    $node_b = $instance->attachToSlot($node_a, 'slot_1', 0, 'test_group_source', ['value' => 'B']);

    $index = $instance->getPathIndex();

    self::assertArrayHasKey($node_a, $index);
    self::assertArrayHasKey($node_b, $index);
    self::assertSame([0], $index[$node_a]['path']);
    self::assertNull($index[$node_a]['parent']);
    self::assertSame($node_a, $index[$node_b]['parent']);
    self::assertNotEmpty($index[$node_b]['path']);
  }

  /**
   * Test remove cascades to descendants at the Instance level.
   */
  public function testRemoveCascadesDescendants(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = ['component' => ['component_id' => 'display_builder_test:test_1']];
    $node_parent = $instance->attachToRoot(0, 'component', $comp_data);
    $node_child = $instance->attachToSlot($node_parent, 'slot_1', 0, 'test_group_source', ['value' => 'child']);

    $instance->remove($node_parent);

    // Both parent and child paths must be gone from the index.
    $index = $instance->getPathIndex();
    self::assertArrayNotHasKey($node_parent, $index);
    self::assertArrayNotHasKey($node_child, $index);
  }

}
