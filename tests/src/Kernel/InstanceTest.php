<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\ProfileInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
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
    $this->installConfig(['display_builder']);
  }

  /**
   * Test the ::id() method.
   */
  public function testId(): void {
    $instance = $this->createDisplayBuilderInstance(NULL, 'test_id');
    self::assertSame('test_id', $instance->id());
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
   * Test the ::isNew() method.
   */
  public function testIsNew(): void {
    $instance = Instance::create([
      'label' => 'Test Instance',
    ]);
    self::assertTrue($instance->isNew());

    $instance = Instance::create([
      'id' => 'foo__test',
      'label' => 'Test Instance',
    ]);
    self::assertFalse($instance->isNew());
  }

  /**
   * Test the ::getProfile() and ::setProfile() methods.
   */
  public function testProfile(): void {
    $this->createDisplayBuilderProfile('test_profile');
    $instance = $this->createDisplayBuilderInstance('test_profile');

    $loaded_profile = $instance->getProfile();
    self::assertInstanceOf(ProfileInterface::class, $loaded_profile);
    self::assertSame('test_profile', $loaded_profile->id());

    $this->createDisplayBuilderProfile('new_profile');
    $instance->setProfile('new_profile');
    self::assertSame('new_profile', $instance->getProfile()->id());
  }

  /**
   * Test the ::toArray() method.
   */
  public function testToArray(): void {
    $instance = $this->createDisplayBuilderInstance(NULL, 'test_id');
    $array = $instance->toArray();

    self::assertIsArray($array);
    self::assertSame('test_id', $array['id']);
    self::assertArrayHasKey('profileId', $array);
    self::assertArrayHasKey('contexts', $array);
    self::assertArrayHasKey('past', $array);
    self::assertArrayHasKey('present', $array);
    self::assertArrayHasKey('future', $array);
    self::assertArrayHasKey('save', $array);
  }

  /**
   * Test the ::getContexts() method.
   */
  public function testContexts(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Default contexts should be empty or from profile.
    self::assertEmpty($instance->getContexts());

    $context_definition = new ContextDefinition('string', 'Test Context');
    $context = new Context($context_definition, 'test value');

    // We can't directly set contexts on Instance as there's no setContexts.
    // However, it's passed via Instance::create().
    $instance = Instance::create([
      'id' => 'test_id',
      'contexts' => ['test' => $context],
    ]);

    $contexts = $instance->getContexts();
    self::assertArrayHasKey('test', $contexts);
    self::assertSame('test value', $contexts['test']->getContextValue());
  }

  /**
   * Test context requirements methods.
   */
  public function testContextRequirements(): void {
    $instance = $this->createDisplayBuilderInstance();

    self::assertFalse($instance->canSaveContextsRequirement());
    self::assertFalse($instance->hasSaveContextsRequirement('any'));

    $contexts = RequirementsContext::addToContext(['key1'], []);

    $instance = Instance::create([
      'id' => 'test_id',
      'contexts' => $contexts,
    ]);

    self::assertTrue($instance->canSaveContextsRequirement());
    self::assertTrue($instance->hasSaveContextsRequirement('key1'));
    self::assertFalse($instance->hasSaveContextsRequirement('key2'));
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
   * Test isNodeAlreadyInSlot.
   */
  public function testIsNodeAlreadyInSlot(): void {
    $instance = $this->createDisplayBuilderInstance();
    $comp_data = [
      'component' => [
        'component_id' => 'display_builder_test:test_1',
      ],
    ];
    $node_id_parent = $instance->attachToRoot(0, 'component', $comp_data);
    $node_id_child = $instance->attachToSlot($node_id_parent, 'slot_1', 0, 'test_group_source', ['value' => 'child']);

    // Case 1: Node is in the slot.
    self::assertTrue($instance->isNodeAlreadyInSlot($node_id_parent, 'slot_1', $node_id_child));

    // Case 2: Node is NOT in a different slot (even if parent is correct).
    self::assertFalse($instance->isNodeAlreadyInSlot($node_id_parent, 'non_existent_slot', $node_id_child));

    // Case 3: Incorrect parent ID.
    self::assertFalse($instance->isNodeAlreadyInSlot('wrong_parent', 'slot_1', $node_id_child));

    // Case 4: Parent node exists but doesn't support slots (test_group_source).
    $node_id_no_slots = $instance->attachToRoot(1, 'test_group_source', ['value' => 'no_slots']);
    // Try to check if something is in a "slot" of a plugin that has no slots.
    self::assertFalse($instance->isNodeAlreadyInSlot($node_id_no_slots, 'slot_1', $node_id_child));
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

    self::assertSame('', $instance->getParentId($node_id_child));
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
    self::assertEmpty($instance->getCurrentState());
  }

  /**
   * Test setSource exception when node ID mismatch.
   */
  public function testSetSourceException(): void {
    $instance = $this->createDisplayBuilderInstance();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Node ID mismatch');
    $instance->setSource('non_existent_id', 'test_group_source', []);
  }

}
