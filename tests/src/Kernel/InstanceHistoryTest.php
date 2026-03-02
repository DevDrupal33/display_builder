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
 * Tests the history functionality of the Instance entity.
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
  public function restore(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];
    $modifiedData = ['component' => ['id' => 'modified_component']];

    // Set save data.
    $instance->setSave($testData);

    // Modify current state.
    $instance->setNewPresent($modifiedData, 'Modified state');
    self::assertSame($modifiedData, $instance->getCurrentState());

    // Restore to save.
    $instance->restore();
    self::assertSame($testData, $instance->getCurrentState());
    self::assertSame('Back to saved data.', $instance->getCurrent()->log);
  }

  /**
   * Test the ::clear() method.
   */
  public function testClear(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create some history.
    $state1 = ['component' => ['id' => 'state1']];
    $state2 = ['component' => ['id' => 'state2']];

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');

    // Undo once to create future history.
    $instance->undo();

    // Verify we have history.
    self::assertNotNull($instance->getCurrent());
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // Clear history.
    $instance->clear();

    // Verify history is cleared but current state remains.
    self::assertNotNull($instance->getCurrent());
    self::assertSame($state1, $instance->getCurrentState());
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());
  }

  /**
   * Test the ::getCountFuture() method.
   *
   * Tests that future array is properly managed during history operations.
   */
  public function testFutureArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create multiple states.
    for ($i = 1; $i <= 3; ++$i) {
      $state = ['component' => ['id' => 'state' . $i]];
      $instance->setNewPresent($state, "State {$i}");
    }

    // Undo twice to create future history.
    $instance->undo();
    $instance->undo();

    // Since getFuture() doesn't exist in HistoryInterface, we can't directly
    // test the array, instead, we'll test the count which is available.
    self::assertSame(2, $instance->getCountFuture());

    // Test that future count changes during redo.
    $instance->redo();
    self::assertSame(1, $instance->getCountFuture());
  }

  /**
   * Test the ::getUniqId() method.
   */
  public function testGetUniqId(): void {
    $data1 = ['component' => ['id' => 'test1']];
    $data2 = ['component' => ['id' => 'test2']];
    // Same as data1.
    $data3 = ['component' => ['id' => 'test1']];

    // Test that identical data produces same hash.
    $hash1 = Instance::getUniqId($data1);
    $hash3 = Instance::getUniqId($data3);
    self::assertSame($hash1, $hash3);

    // Test that different data produces different hash.
    $hash2 = Instance::getUniqId($data2);
    self::assertNotEquals($hash1, $hash2);

    // Test that hash is an integer.
    self::assertIsInt($hash1);
    self::assertIsInt($hash2);
  }

  /**
   * Test the ::getUsers() method.
   */
  public function testGetUsers(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create states with different users.
    $state1 = ['component' => ['id' => 'state1']];
    $state2 = ['component' => ['id' => 'state2']];
    $state3 = ['component' => ['id' => 'state3']];

    // Mock current user to return different IDs.
    $mockCurrentUser = $this->prophesize(AccountInterface::class);
    $mockCurrentUser->id()->willReturn(1, 2, 3);

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
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // 3. Second action.
    $instance->setNewPresent($state2, 'Step 2');
    self::assertSame(2, $instance->getCountPast());

    // 4. Test Undo.
    $instance->undo();
    self::assertSame($state1, $instance->getCurrentState());
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // 5. Test Redo.
    $instance->redo();
    self::assertSame($state2, $instance->getCurrentState());
    self::assertSame(2, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // 6. Test Save and Restore.
    $instance->setSave($state1);
    self::assertTrue($instance->hasSave());
    self::assertFalse($instance->saveIsCurrent());

    $instance->restore();
    self::assertTrue($instance->saveIsCurrent());
    self::assertCount(1, $instance->getCurrentState());
  }

  /**
   * Test the ::getCountPast() method.
   */
  public function testHashDuplicateDetection(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];

    // Set initial state.
    $instance->setNewPresent($testData, 'First state');
    $initialPastCount = $instance->getCountPast();

    // Try to set same data again - should be ignored due to hash check.
    $instance->setNewPresent($testData, 'Duplicate state', TRUE);
    self::assertSame($initialPastCount, $instance->getCountPast(), 'Duplicate state should be ignored');

    // Set different data - should be added.
    $differentData = ['component' => ['id' => 'different_component']];
    $instance->setNewPresent($differentData, 'Different state', TRUE);
    self::assertGreaterThan($initialPastCount, $instance->getCountPast(), 'Different state should be added');
  }

  /**
   * Tests ::getCountPast() hash-based duplicate with check_hash disabled.
   */
  public function testHashDuplicateDetectionDisabled(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];

    // Set initial state.
    $instance->setNewPresent($testData, 'First state');
    $initialPastCount = $instance->getCountPast();

    // Try to set same data again with check_hash disabled - should be added.
    $instance->setNewPresent($testData, 'Duplicate state', FALSE);
    self::assertGreaterThan($initialPastCount, $instance->getCountPast(), 'Duplicate state should be added when hash check is disabled');
  }

  /**
   * Tests history limit (MAX_HISTORY).
   */
  public function testHistoryLimit(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create more states than MAX_HISTORY (10)
    for ($i = 1; $i <= 15; ++$i) {
      $state = ['component' => ['id' => 'state' . $i]];
      $instance->setNewPresent($state, "State {$i}");
      $instance->setNewPresent($state, "State {$i}");
    }

    // Should only keep last 10 states.
    self::assertSame(10, $instance->getCountPast());

    // Verify oldest states are removed (should not contain state1 or state2)
    // Since getPast() doesn't exist in HistoryInterface, we'll test this
    // indirectly by verifying the count limit is enforced.
    self::assertSame(10, $instance->getCountPast());
  }

  /**
   * Tests initial state of history.
   */
  public function testInitialHistoryState(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Test initial state.
    self::assertNull($instance->getCurrent());
    self::assertEmpty($instance->getCurrentState());
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());
    self::assertFalse($instance->hasSave());
  }

  /**
   * Test the ::getCountPast() method.
   *
   * Tests that past array is properly managed during history operations.
   */
  public function testPastArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create multiple states.
    for ($i = 1; $i <= 5; ++$i) {
      $state = ['component' => ['id' => 'state' . $i]];
      $instance->setNewPresent($state, "State {$i}");
    }

    // Since getPast() doesn't exist in HistoryInterface, we can't directly test
    // the array, instead, we'll test the count which is available.
    self::assertSame(5, $instance->getCountPast());

    // Test that past count decreases during undo.
    $instance->undo();
    self::assertSame(4, $instance->getCountPast());
  }

  /**
   * Test the ::postCreate() method.
   */
  public function testPostCreateWithPresentState(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Set initial present state.
    // Root level must be an array list because it is a collection of sources.
    $testData = [['component' => ['id' => 'test_component']]];
    $time = \time();
    $instance->present = new HistoryStep($testData, 123, 'Test', $time, 3);

    $mockStorage = $this->prophesize(EntityStorageInterface::class);

    $instance->postCreate($mockStorage->reveal());

    // Verify that path index was built.
    $pathIndex = $instance->getPathIndex();
    // The path index should contain entries for the components in the data
    // Since we're using test_component, it should have a node_id.
    self::assertNotEmpty($pathIndex);
    // Check that the path index contains at least one entry.
    self::assertNotEmpty(\array_keys($pathIndex));
    self::assertSame([0], \reset($pathIndex)['path']);

    self::assertIsInt($instance->present->hash);
    self::assertSame('Test', $instance->present->log);
    self::assertSame($time, $instance->present->time);
    self::assertSame(3, $instance->present->user);
  }

  /**
   * Test the ::redo() method.
   */
  public function testRedo(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create multiple states.
    $state1 = ['component' => ['id' => 'state1']];
    $state2 = ['component' => ['id' => 'state2']];
    $state3 = ['component' => ['id' => 'state3']];

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');
    $instance->setNewPresent($state3, 'State 3');

    // Undo to state 1.
    $instance->undo();
    $instance->undo();
    self::assertSame($state1, $instance->getCurrentState());

    // Redo once.
    $instance->redo();
    self::assertSame($state2, $instance->getCurrentState());
    self::assertSame(2, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // Redo again.
    $instance->redo();
    self::assertSame($state3, $instance->getCurrentState());
    self::assertSame(3, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // Try to redo when at end.
    $instance->redo();
    self::assertSame($state3, $instance->getCurrentState());
  }

  /**
   * Test the ::saveIsCurrent() method.
   */
  public function testSaveIsCurrent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];

    // Initially Save match init, so is true.
    self::assertTrue($instance->saveIsCurrent());

    // Set save data.
    $instance->setSave($testData);
    self::assertFalse($instance->saveIsCurrent());

    // Modify state - should no longer be current.
    $modifiedData = ['component' => ['id' => 'modified_component']];
    $instance->setNewPresent($modifiedData, 'Modified state');

    // Note: There may be edge cases where saveIsCurrent returns unexpected
    // results.
    // The important thing is that restore() works correctly, which it does.
    self::assertFalse($instance->saveIsCurrent());

    // Restore - should be current again.
    $instance->restore();
    // After restore, present should equal save, so saveIsCurrent() should be
    // true.
    self::assertTrue($instance->saveIsCurrent());

    // Test edge case: when both present and save are null, should return true
    // This covers the null-safe operator behavior.
    $instance2 = $this->createDisplayBuilderInstance();
    self::assertTrue($instance2->saveIsCurrent());
  }

  /**
   * Test the ::setNewPresent() method.
   */
  public function testSetNewPresent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];

    // Set initial state.
    $instance->setNewPresent($testData, 'Initial state');

    // Verify state is set.
    $current = $instance->getCurrent();
    self::assertInstanceOf(HistoryStep::class, $current);
    self::assertSame($testData, $current->data);
    self::assertSame('Initial state', $current->log);
    self::assertIsInt($current->hash);
    self::assertIsInt($current->time);
    self::assertGreaterThanOrEqual(0, $current->user);

    // Verify current state.
    self::assertSame($testData, $instance->getCurrentState());
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());
  }

  /**
   * Test the ::setSave() method.
   */
  public function testSetSave(): void {
    $instance = $this->createDisplayBuilderInstance();
    // Root level must be an array list because it is a collection of sources.
    $testData = [['component' => ['id' => 'test_component']]];

    // Initially no save.
    self::assertFalse($instance->hasSave());

    // Set save data.
    $instance->setSave($testData);

    // Verify save is set.
    self::assertTrue($instance->hasSave());
    self::assertNotNull($instance->save);
    self::assertInstanceOf(HistoryStep::class, $instance->save);

    self::assertArrayHasKey('node_id', $instance->save->data[0]);
    self::assertSame($testData[0]['component']['id'], $instance->save->data[0]['component']['id']);

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
    $instance->setNewPresent($state2, 'State 2');
    $instance->setNewPresent($state3, 'State 3');

    // Verify we're at state 3.
    self::assertSame($state3, $instance->getCurrentState());
    self::assertSame(3, $instance->getCountPast());
    self::assertSame(0, $instance->getCountFuture());

    // Undo once.
    $instance->undo();
    self::assertSame($state2, $instance->getCurrentState());
    self::assertSame(2, $instance->getCountPast());
    self::assertSame(1, $instance->getCountFuture());

    // Undo again.
    $instance->undo();
    self::assertSame($state1, $instance->getCurrentState());
    self::assertSame(1, $instance->getCountPast());
    self::assertSame(2, $instance->getCountFuture());

    // Undo to beginning.
    $instance->undo();
    self::assertNull($instance->getCurrent());
    self::assertEmpty($instance->getCurrentState());
    self::assertSame(0, $instance->getCountPast());
    self::assertSame(3, $instance->getCountFuture());

    // Try to undo when at beginning.
    $instance->undo();
    self::assertNull($instance->getCurrent());
  }

}
