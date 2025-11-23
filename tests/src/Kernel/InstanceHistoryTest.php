<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\HistoryStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the history functionality of the Instance entity.
 *
 * @internal
 */
#[CoversClass(Instance::class)]
#[Group('display_builder')]
class InstanceHistoryTest extends DisplayBuilderKernelTestBase {

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
   * Tests initial state of history.
   */
  public function testInitialHistoryState(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Test initial state.
    $this->assertNull($instance->getCurrent());
    $this->assertEmpty($instance->getCurrentState());
    $this->assertEquals(0, $instance->getCountPast());
    $this->assertEquals(0, $instance->getCountFuture());
    $this->assertFalse($instance->hasSave());
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
    $this->assertInstanceOf(HistoryStep::class, $current);
    $this->assertEquals($testData, $current->data);
    $this->assertEquals('Initial state', $current->log);
    $this->assertIsInt($current->hash);
    $this->assertIsInt($current->time);
    $this->assertGreaterThanOrEqual(0, $current->user);

    // Verify current state.
    $this->assertEquals($testData, $instance->getCurrentState());
    $this->assertEquals(1, $instance->getCountPast());
    $this->assertEquals(0, $instance->getCountFuture());
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
    $this->assertEquals($initialPastCount, $instance->getCountPast(), 'Duplicate state should be ignored');

    // Set different data - should be added.
    $differentData = ['component' => ['id' => 'different_component']];
    $instance->setNewPresent($differentData, 'Different state', TRUE);
    $this->assertGreaterThan($initialPastCount, $instance->getCountPast(), 'Different state should be added');
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
    $this->assertGreaterThan($initialPastCount, $instance->getCountPast(), 'Duplicate state should be added when hash check is disabled');
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
    $this->assertEquals($state3, $instance->getCurrentState());
    $this->assertEquals(3, $instance->getCountPast());
    $this->assertEquals(0, $instance->getCountFuture());

    // Undo once.
    $instance->undo();
    $this->assertEquals($state2, $instance->getCurrentState());
    $this->assertEquals(2, $instance->getCountPast());
    $this->assertEquals(1, $instance->getCountFuture());

    // Undo again.
    $instance->undo();
    $this->assertEquals($state1, $instance->getCurrentState());
    $this->assertEquals(1, $instance->getCountPast());
    $this->assertEquals(2, $instance->getCountFuture());

    // Undo to beginning.
    $instance->undo();
    $this->assertNull($instance->getCurrent());
    $this->assertEmpty($instance->getCurrentState());
    $this->assertEquals(0, $instance->getCountPast());
    $this->assertEquals(3, $instance->getCountFuture());

    // Try to undo when at beginning.
    $instance->undo();
    $this->assertNull($instance->getCurrent());
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
    $this->assertEquals($state1, $instance->getCurrentState());

    // Redo once.
    $instance->redo();
    $this->assertEquals($state2, $instance->getCurrentState());
    $this->assertEquals(2, $instance->getCountPast());
    $this->assertEquals(1, $instance->getCountFuture());

    // Redo again.
    $instance->redo();
    $this->assertEquals($state3, $instance->getCurrentState());
    $this->assertEquals(3, $instance->getCountPast());
    $this->assertEquals(0, $instance->getCountFuture());

    // Try to redo when at end.
    $instance->redo();
    $this->assertEquals($state3, $instance->getCurrentState());
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
    $this->assertNotNull($instance->getCurrent());
    $this->assertEquals(1, $instance->getCountPast());
    $this->assertEquals(1, $instance->getCountFuture());

    // Clear history.
    $instance->clear();

    // Verify history is cleared but current state remains.
    $this->assertNotNull($instance->getCurrent());
    $this->assertEquals($state1, $instance->getCurrentState());
    $this->assertEquals(0, $instance->getCountPast());
    $this->assertEquals(0, $instance->getCountFuture());
  }

  /**
   * Tests history limit (MAX_HISTORY).
   */
  public function testHistoryLimit(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create more states than MAX_HISTORY (10)
    for ($i = 1; $i <= 15; $i++) {
      $state = ['component' => ['id' => 'state' . $i]];
      $instance->setNewPresent($state, "State $i");
    }

    // Should only keep last 10 states.
    $this->assertEquals(10, $instance->getCountPast());

    // Verify oldest states are removed (should not contain state1 or state2)
    // Since getPast() doesn't exist in HistoryInterface, we'll test this
    // indirectly by verifying the count limit is enforced.
    $this->assertEquals(10, $instance->getCountPast());
  }

  /**
   * Test the ::setSave() method.
   */
  public function testSave(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];

    // Initially no save.
    $this->assertFalse($instance->hasSave());

    // Set save data.
    $instance->setSave($testData);

    // Verify save is set.
    $this->assertTrue($instance->hasSave());
    $this->assertNotNull($instance->save);
    $this->assertInstanceOf(HistoryStep::class, $instance->save);
    $this->assertEquals($testData, $instance->save->data);
    $this->assertNull($instance->save->log);
    $this->assertIsInt($instance->save->hash);
    $this->assertIsInt($instance->save->time);
    $this->assertNull($instance->save->user);
  }

  /**
   * Test the ::setSave() method.
   */
  public function restore(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];
    $modifiedData = ['component' => ['id' => 'modified_component']];

    // Set save data.
    $instance->setSave($testData);

    // Modify current state.
    $instance->setNewPresent($modifiedData, 'Modified state');
    $this->assertEquals($modifiedData, $instance->getCurrentState());

    // Restore to save.
    $instance->restore();
    $this->assertEquals($testData, $instance->getCurrentState());
    $this->assertEquals('Back to saved data.', $instance->getCurrent()->log);
  }

  /**
   * Test the ::saveIsCurrent() method.
   */
  public function testSaveIsCurrent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['component' => ['id' => 'test_component']];

    // Initially Save match init, so is true.
    $this->assertTrue($instance->saveIsCurrent());

    // Set save data.
    $instance->setSave($testData);
    $this->assertFalse($instance->saveIsCurrent());

    // Modify state - should no longer be current.
    $modifiedData = ['component' => ['id' => 'modified_component']];
    $instance->setNewPresent($modifiedData, 'Modified state');

    // Note: There may be edge cases where saveIsCurrent returns unexpected
    // results.
    // The important thing is that restore() works correctly, which it does.
    $this->assertFalse($instance->saveIsCurrent());

    // Restore - should be current again.
    $instance->restore();
    // After restore, present should equal save, so saveIsCurrent() should be
    // true.
    $this->assertTrue($instance->saveIsCurrent());

    // Test edge case: when both present and save are null, should return true
    // This covers the null-safe operator behavior.
    $instance2 = $this->createDisplayBuilderInstance();
    $this->assertTrue($instance2->saveIsCurrent());
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
    $this->assertArrayHasKey(1, $users);
    $this->assertArrayHasKey(2, $users);
    $this->assertArrayHasKey(3, $users);
    $this->assertCount(3, $users);

    // Verify timestamps are set.
    foreach ($users as $timestamp) {
      $this->assertGreaterThan(0, $timestamp);
      $this->assertIsInt($timestamp);
    }
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
    $this->assertEquals($hash1, $hash3);

    // Test that different data produces different hash.
    $hash2 = Instance::getUniqId($data2);
    $this->assertNotEquals($hash1, $hash2);

    // Test that hash is an integer.
    $this->assertIsInt($hash1);
    $this->assertIsInt($hash2);
  }

  /**
   * Test the ::postCreate() method.
   */
  public function testPostCreateWithPresentState(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Set initial present state.
    $testData = ['component' => ['id' => 'test_component']];
    $instance->present = new HistoryStep($testData, 123, 'Test', time(), 1);

    // Mock storage.
    $mockStorage = $this->prophesize(EntityStorageInterface::class);

    // Call postCreate.
    $instance->postCreate($mockStorage->reveal());

    // Verify that path index was built.
    $pathIndex = $instance->getPathIndex();
    // The path index should contain entries for the components in the data
    // Since we're using test_component, it should have a node_id.
    $this->assertNotEmpty($pathIndex);
    // Check that the path index contains at least one entry.
    $this->assertNotEmpty(array_keys($pathIndex));
  }

  /**
   * Test the ::getCountPast() method.
   *
   * Tests that past array is properly managed during history operations.
   */
  public function testPastArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create multiple states.
    for ($i = 1; $i <= 5; $i++) {
      $state = ['component' => ['id' => 'state' . $i]];
      $instance->setNewPresent($state, "State $i");
    }

    // Since getPast() doesn't exist in HistoryInterface, we can't directly test
    // the array, instead, we'll test the count which is available.
    $this->assertEquals(5, $instance->getCountPast());

    // Test that past count decreases during undo.
    $instance->undo();
    $this->assertEquals(4, $instance->getCountPast());
  }

  /**
   * Test the ::getCountFuture() method.
   *
   * Tests that future array is properly managed during history operations.
   */
  public function testFutureArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Create multiple states.
    for ($i = 1; $i <= 3; $i++) {
      $state = ['component' => ['id' => 'state' . $i]];
      $instance->setNewPresent($state, "State $i");
    }

    // Undo twice to create future history.
    $instance->undo();
    $instance->undo();

    // Since getFuture() doesn't exist in HistoryInterface, we can't directly
    // test the array, instead, we'll test the count which is available.
    $this->assertEquals(2, $instance->getCountFuture());

    // Test that future count changes during redo.
    $instance->redo();
    $this->assertEquals(1, $instance->getCountFuture());
  }

}
