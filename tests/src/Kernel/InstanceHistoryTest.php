<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\HistoryStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the history management of the Instance entity.
 *
 * @internal
 */
#[CoversClass(Instance::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class InstanceHistoryTest extends DisplayBuilderKernelTestBase {

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
   * Test the ::restore() method.
   */
  public function testRestore(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['id' => 'test_component']];
    $modifiedData = [['id' => 'modified_component']];

    // Set save data.
    $instance->setSave($testData);
    $saved_indexed = $instance->save->data;

    // Modify current state.
    $instance->setNewPresent($modifiedData, 'Modified state');
    self::assertSame($modifiedData[0]['id'], $instance->getCurrentState()[0]['id']);

    // Restore to save.
    $instance->restore();
    self::assertSame($saved_indexed, $instance->getCurrentState());
    self::assertSame('Back to saved data.', $instance->getCurrent()->log);
  }

  /**
   * Test the ::clear() method.
   */
  public function testClear(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create some history.
    $state1 = [['id' => 'state1']];
    $state2 = [['id' => 'state2']];

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');

    // Undo once to create future history.
    $instance->undo();

    // Verify we have history.
    self::assertNotNull($instance->getCurrent());
    // past=[NULL, state1], present=state2, then undo()
    // -> present=state1, past=[NULL], future=[state2].
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // Clear history.
    $instance->clear();

    // Verify history is cleared but current state remains.
    self::assertNotNull($instance->getCurrent());
    self::assertSame('state1', $instance->getCurrentState()[0]['id']);
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());
  }

  /**
   * Test the ::getCountFuture() method.
   */
  public function testFutureArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();
    $state1 = [['id' => 'state1']];
    $state2 = [['id' => 'state2']];
    $state3 = [['id' => 'state3']];

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');
    $instance->setNewPresent($state3, 'State 3');

    self::assertSame(0, $instance->getCountFuture());

    $instance->undo();
    self::assertSame(1, $instance->getCountFuture());

    $instance->undo();
    self::assertSame(2, $instance->getCountFuture());

    // Redo should decrease future count.
    $instance->redo();
    self::assertSame(1, $instance->getCountFuture());
  }

  /**
   * Test the ::getUsers() method.
   */
  public function testGetUsers(): void {
    $instance = $this->createDisplayBuilderInstance();
    $state1 = [['source_id' => 'attributes', 'source' => []]];
    $state2 = [['source_id' => 'textfield', 'source' => []]];
    $state3 = [['source_id' => 'token', 'source' => []]];

    // Create a mock user service to return different user IDs.
    $mockCurrentUser = $this->prophesize(AccountInterface::class);
    // Sequence of user IDs: 1, 2, 3.
    $mockCurrentUser->id()->willReturn(1, 2, 3, 1, 2, 3);

    // Set up instance with mocked user service.
    $instance->currentUser = $mockCurrentUser->reveal();

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');
    $instance->setNewPresent($state3, 'State 3');

    // Test get users.
    $users = $instance->getUsers();
    self::assertArrayHasKey(1, $users);
    self::assertArrayHasKey(2, $users);
    self::assertArrayHasKey(3, $users);
    self::assertCount(3, $users);

    // Verify timestamps are set.
    foreach ($users as $timestamp) {
      self::assertGreaterThan(0, $timestamp);
      self::assertIsInt($timestamp);
    }

    // Test with anonymous user (ID 0).
    $instance2 = $this->createDisplayBuilderInstance();
    // Default kernel user is anonymous (ID 0).
    $instance2->setNewPresent($state1, 'State 1');
    $users2 = $instance2->getUsers();
    self::assertArrayHasKey(0, $users2, 'Anonymous user (ID 0) should be included in getUsers()');
  }

  /**
   * Test history management: past, present, future, and save.
   */
  public function testHistory(): void {
    $instance = $this->createDisplayBuilderInstance();
    $state1 = [['source_id' => 'attributes', 'source' => []]];
    $state2 = [['source_id' => 'textfield', 'source' => []]];

    // 1. Initial state.
    self::assertNull($instance->getCurrent());
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // 2. First action.
    $instance->setNewPresent($state1, 'Step 1');
    // past=[NULL], filtered count = 0.
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // 3. Second action.
    $instance->setNewPresent($state2, 'Step 2');
    // past=[NULL, Step 1], filtered count = 1.
    self::assertSame(1, $instance->getCountPast());

    // 4. Test Undo.
    $instance->undo();
    self::assertSame('attributes', $instance->getCurrentState()[0]['source_id']);
    // past=[NULL], filtered count = 0.
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // 5. Test Redo.
    $instance->redo();
    self::assertSame('textfield', $instance->getCurrentState()[0]['source_id']);
    // past=[NULL, Step 1], filtered count = 1.
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // 6. Test Save and Restore.
    $instance->setSave($state1);
    self::assertTrue($instance->hasSave());
    self::assertFalse($instance->saveIsCurrent());

    $instance->restore();
    self::assertTrue($instance->saveIsCurrent());
    self::assertCount(1, $instance->getCurrentState());
    self::assertSame('attributes', $instance->getCurrentState()[0]['source_id']);
  }

  /**
   * Test the ::getCountPast() method.
   */
  public function testHashDuplicateDetection(): void {
    $instance = $this->createDisplayBuilderInstance();
    // Use data with node_id to ensure hash is identical across calls.
    $testData = [['node_id' => 'fixed_id', 'source_id' => 'textfield', 'source' => []]];

    // Set initial state. past=[NULL], count=0.
    $instance->setNewPresent($testData, 'First state');
    self::assertSame(0, $instance->getCountPast());

    // Try to set same data again - should be ignored due to hash check.
    $instance->setNewPresent($testData, 'Duplicate state', TRUE);
    self::assertSame(0, $instance->getCountPast(), 'Duplicate state should be ignored');

    // Set different data - should be added.
    $differentData = [['node_id' => 'other_id', 'source_id' => 'textfield', 'source' => []]];
    $instance->setNewPresent($differentData, 'Different state', TRUE);
    // past=[NULL, state1], count=1.
    self::assertSame(1, $instance->getCountPast(), 'Different state should be added');
  }

  /**
   * Test duplicate detection when disabled.
   */
  public function testHashDuplicateDetectionDisabled(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['node_id' => 'fixed_id', 'source_id' => 'textfield', 'source' => []]];

    // Set initial state. past=[NULL], count=0.
    $instance->setNewPresent($testData, 'First state');
    self::assertSame(0, $instance->getCountPast());

    // Try to set same data again with check_hash = FALSE - should be added.
    $instance->setNewPresent($testData, 'Duplicate state', FALSE);
    // past=[NULL, state1], count=1.
    self::assertSame(1, $instance->getCountPast());
  }

  /**
   * Test the history limit.
   */
  public function testHistoryLimit(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Max history is 10.
    for ($i = 0; $i < 15; ++$i) {
      $instance->setNewPresent([['id' => $i]], "Step {$i}");
    }

    // Should only keep last 10 in past.
    self::assertSame(10, $instance->getCountPast());
  }

  /**
   * Test the ::getCountPast() method.
   */
  public function testPastArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();
    $state1 = [['id' => 'state1']];
    $state2 = [['id' => 'state2']];

    $instance->setNewPresent($state1, 'State 1');
    // past=[NULL], filtered count = 0.
    self::assertSame(0, $instance->getCountPast());

    $instance->setNewPresent($state2, 'State 2');
    // past=[NULL, Step 1], filtered count = 1.
    self::assertSame(1, $instance->getCountPast());

    $instance->undo();
    // past=[NULL], filtered count = 0.
    self::assertSame(0, $instance->getCountPast());
  }

  /**
   * Test the ::postCreate() method.
   */
  public function testPostCreateWithPresentState(): void {
    $storage = $this->prophesize(EntityStorageInterface::class);
    $testData = [['source_id' => 'textfield', 'source' => ['value' => 'test']]];

    $instance = Instance::create([
      'id' => 'test_instance',
      'present' => new HistoryStep($testData, 0, 'Initial', \time(), 1),
    ]);

    // postCreate should index the present data.
    $instance->postCreate($storage->reveal());

    self::assertNotNull($instance->present);
    self::assertArrayHasKey('node_id', $instance->present->data[0]);
    self::assertSame('test', $instance->present->data[0]['source']['value']);
    self::assertNotSame(0, $instance->present->hash);
  }

  /**
   * Test the ::saveIsCurrent() method.
   */
  public function testSaveIsCurrent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['node_id' => 'fixed_id', 'source_id' => 'textfield', 'source' => []]];

    // Initially both NULL, so they are current.
    self::assertTrue($instance->saveIsCurrent());

    $instance->setNewPresent($testData, 'Present');
    // Save is NULL, present is not NULL.
    self::assertFalse($instance->saveIsCurrent());

    $instance->setSave($testData);
    self::assertTrue($instance->saveIsCurrent());

    $instance->setNewPresent([['node_id' => 'other_id', 'source_id' => 'textfield']], 'Modified');
    self::assertFalse($instance->saveIsCurrent());
  }

  /**
   * Test the ::setNewPresent() method.
   */
  public function testSetNewPresent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['id' => 'test_component']];
    $time = \time();

    // Set initial state.
    $instance->setNewPresent($testData, 'Initial state');

    // Verify state is set.
    $current = $instance->getCurrent();
    self::assertInstanceOf(HistoryStep::class, $current);
    self::assertSame('test_component', $current->data[0]['id']);
    self::assertArrayHasKey('node_id', $current->data[0]);
    self::assertSame('Initial state', $current->log);
    self::assertIsInt($current->hash);
    self::assertGreaterThanOrEqual($time, $current->time);
    self::assertGreaterThanOrEqual(0, $current->user);

    // Verify current state.
    self::assertSame('test_component', $instance->getCurrentState()[0]['id']);
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());
  }

  /**
   * Test the ::setSave() method.
   */
  public function testSetSave(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['source_id' => 'textfield', 'source' => ['value' => 'test']]];

    $instance->setSave($testData);

    self::assertTrue($instance->hasSave());
    self::assertNotNull($instance->save);
    self::assertInstanceOf(HistoryStep::class, $instance->save);

    self::assertArrayHasKey('node_id', $instance->save->data[0]);
    self::assertSame('test', $instance->save->data[0]['source']['value']);

    self::assertNull($instance->save->log);
    self::assertIsInt($instance->save->hash);
    self::assertIsInt($instance->save->time);
    self::assertNull($instance->save->user);
  }

  /**
   * Test the ::undo() method.
   */
  public function testUndo(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create multiple states.
    $state1 = ['component' => ['id' => 'state1']];
    $state2 = ['component' => ['id' => 'state2']];
    $state3 = ['component' => ['id' => 'state3']];

    $instance->setNewPresent($state1, 'State 1');
    // $instance->save();
    self::assertSame('state1', $instance->getCurrentState()[0]['id']);
    $instance->setNewPresent($state2, 'State 2');
    // $instance->save();
    self::assertSame('state2', $instance->getCurrentState()[0]['id']);
    $instance->setNewPresent($state3, 'State 3');
    // $instance->save();
    self::assertSame('state3', $instance->getCurrentState()[0]['id']);

    // Verify we're at state 3.
    self::assertSame('state3', $instance->getCurrentState()[0]['id']);
    // past=[NULL, Step 1, Step 2], count=2.
    self::assertSame(2, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // Undo 1: present=state2, past=[NULL, state1].
    $instance->undo();
    self::assertSame('state2', $instance->getCurrentState()[0]['id']);
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // Undo 2: present=state1, past=[NULL].
    $instance->undo();
    self::assertSame('state1', $instance->getCurrentState()[0]['id']);
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(2, $instance->getCountFuture());

    // Undo 3: Can not undo last step, nothing happens, present remains state1,
    // past=[NULL].
    $instance->undo();
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(2, $instance->getCountFuture());
  }

}
