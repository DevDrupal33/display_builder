<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\InstanceStorage;
use Drupal\Tests\user\Traits\UserCreationTrait;
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

  use UserCreationTrait;

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
    'display_builder_ui',
  ];

  /**
   * Instance entities storage handler.
   */
  private InstanceStorage $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder', 'display_builder_test']);
    $this->storage = \Drupal::service('entity_type.manager')->getStorage('display_builder_instance');
  }

  /**
   * Test the ::getFuture() method.
   *
   * Tests that future array is properly managed during history operations.
   */
  public function testFutureArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Create multiple states.
    for ($i = 1; $i <= 3; ++$i) {
      $state = $this->makeSource('state_' . $i);
      $instance->setNewPresent($state, "State {$i}");
    }

    // Undo twice to create future history.
    $instance = $this->storage->undo($instance);
    $instance = $this->storage->undo($instance);

    self::assertSame(2, \count($instance->getFuture()));

    // Test that future count changes during redo.
    $instance = $this->storage->redo($instance);
    self::assertSame(1, \count($instance->getFuture()));
  }

  /**
   * Test the ::getUniqId() method.
   */
  public function testGetUniqId(): void {
    $data1 = $this->makeSource('state_1');
    $data2 = $this->makeSource('state_2');
    // Same as data1.
    $data3 = $data1;

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
    self::assertEmpty($instance->getSources());

    // Create states with different users.
    $state1 = $this->makeSource('state_1');
    $state2 = $this->makeSource('state_2');
    $state3 = $this->makeSource('state_3');
    $user1 = $this->createUser();
    $user2 = $this->createUser();
    $user3 = $this->createUser();

    // Set up instance with mocked user service.
    $instance->currentUser = $user1;
    $instance->setNewPresent($state1, 'State 1');
    $instance->currentUser = $user2;
    $instance->setNewPresent($state2, 'State 2');
    $instance->currentUser = $user3;
    $instance->setNewPresent($state3, 'State 3');

    // Test get users.
    $users = $instance->getUsers();
    self::assertArrayHasKey($user1->id(), $users);
    self::assertArrayHasKey($user2->id(), $users);
    self::assertArrayHasKey($user3->id(), $users);
    self::assertCount(3, $users);

    // Verify timestamps are set.
    foreach ($users as $timestamp) {
      self::assertGreaterThan(0, $timestamp);
      self::assertIsInt($timestamp);
    }

    // Test with anonymous user (ID 0).
    $instance2 = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance2->getSources());
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
    self::assertEmpty($instance->getSources());

    $state1 = $this->makeSource('state_1');
    $state2 = $this->makeSource('state_2');

    // 1. Initial state.
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));

    // 2. First action.
    $instance->setNewPresent($state1, 'Step 1');
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));

    // 3. Second action.
    $instance->setNewPresent($state2, 'Step 2');
    self::assertSame(1, \count($instance->getPast()));

    // 4. Test Undo.
    $instance = $this->storage->undo($instance);
    self::assertEquals($state1, $instance->getSources());
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(1, \count($instance->getFuture()));

    // 5. Test Redo.
    $instance = $this->storage->redo($instance);
    self::assertEquals($state2, $instance->getSources());
    self::assertSame(1, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));
  }

  /**
   * Test the ::getPast() method.
   */
  public function testHashDuplicateDetection(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    $testData = $this->makeSource('state_1');

    // Set initial state.
    $instance->setNewPresent($testData, 'First state');
    $initialPastCount = \count($instance->getPast());

    // Try to set same data again - should be ignored due to hash check.
    $instance->setNewPresent($testData, 'Duplicate state', TRUE);
    self::assertSame($initialPastCount, \count($instance->getPast()), 'Duplicate state should be ignored');

    // Set different data - should be added.
    $differentData = $this->makeSource('state_2');
    $instance->setNewPresent($differentData, 'Different state', TRUE);
    self::assertGreaterThan($initialPastCount, \count($instance->getPast()), 'Different state should be added');
  }

  /**
   * Tests ::getPast() hash-based duplicate with check_hash disabled.
   */
  public function testHashDuplicateDetectionDisabled(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    $testData = $this->makeSource('state_1');

    // Set initial state.
    $instance->setNewPresent($testData, 'First state');
    $initialPastCount = \count($instance->getPast());

    // Try to set same data again with check_hash disabled - should be added.
    $instance->setNewPresent($testData, 'Duplicate state', FALSE);
    self::assertGreaterThan($initialPastCount, \count($instance->getPast()), 'Duplicate state should be added when hash check is disabled');
  }

  /**
   * Tests history limit (MAX_HISTORY).
   */
  public function testHistoryLimit(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Create more states than MAX_HISTORY.
    for ($i = 1; $i <= 25; ++$i) {
      $state = $this->makeSource('state_' . $i);
      $instance->setNewPresent($state, "State {$i}");
      $instance->setNewPresent($state, "State {$i}");
    }

    // Should only keep last 20 states.
    self::assertSame(20, \count($instance->getPast()));
  }

  /**
   * Tests initial state of history.
   */
  public function testInitialHistoryState(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Test initial state: no sources and no phantom items from field prototype.
    self::assertEmpty($instance->getSources());
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));
  }

  /**
   * Test the ::getPast() method.
   *
   * Tests that past array is properly managed during history operations.
   */
  public function testPastArrayManagement(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Create multiple states.
    for ($i = 1; $i <= 5; ++$i) {
      $state = $this->makeSource('state_' . $i);
      $instance->setNewPresent($state, "State {$i}");
    }

    self::assertSame(4, \count($instance->getPast()));

    // Test that past count decreases during undo.
    $instance = $this->storage->undo($instance);
    self::assertSame(3, \count($instance->getPast()));
  }

  /**
   * Test the ::postCreate() method.
   */
  public function testPostCreateWithPresentState(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Set initial present state.
    // Root level must be an array list because it is a collection of sources.
    $testData = [['source_id' => 'component', 'node_id' => '1', 'source' => []]];
    $instance->set('sources', $testData);
    $instance->set('hash', 123);
    $instance->setRevisionLogMessage('Test');
    $time = \time();
    $instance->setRevisionCreationTime($time);
    $user = $this->createUser();
    $instance->setRevisionUser($user);

    $mockStorage = $this->prophesize(EntityStorageInterface::class);

    $instance->postCreate($mockStorage->reveal());

    // Verify that path index was built.
    $pathIndex = $instance->getPathIndex();
    // The path index should contain entries for the components in the data
    // Since we're using test_1, it should have a node_id.
    self::assertNotEmpty($pathIndex);
    // Check that the path index contains at least one entry.
    self::assertNotEmpty(\array_keys($pathIndex));
    self::assertSame([0], $pathIndex['1']['path']);

    self::assertIsInt($instance->getHash());
    self::assertSame('Test', $instance->getRevisionLogMessage());
    self::assertSame($time, $instance->getRevisionCreationTime());
    self::assertSame($user->id(), $instance->getRevisionUserId());
  }

  /**
   * Test the ::redo() method.
   */
  public function testRedo(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Create multiple states.
    $state1 = $this->makeSource('state_1');
    $state2 = $this->makeSource('state_2');
    $state3 = $this->makeSource('state_3');

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');
    $instance->setNewPresent($state3, 'State 3');

    // Undo to state 1.
    $instance = $this->storage->undo($instance);
    $instance = $this->storage->undo($instance);
    self::assertEquals($state1, $instance->getSources());

    // Redo once.
    $instance = $this->storage->redo($instance);
    self::assertEquals($state2, $instance->getSources());
    self::assertSame(1, \count($instance->getPast()));
    self::assertSame(1, \count($instance->getFuture()));
    self::assertFalse($instance->isPublished());

    // Redo again.
    $instance = $this->storage->redo($instance);
    self::assertEquals($state3, $instance->getSources());
    self::assertSame(2, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));

    // Try to redo when at end.
    $instance = $this->storage->redo($instance);
    self::assertEquals($state3, $instance->getSources());
  }

  /**
   * Test the ::setNewPresent() method.
   */
  public function testSetNewPresent(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    $testData = $this->makeSource('state_1');

    // Set initial state.
    $instance->setNewPresent($testData, 'Initial state');

    // Verify state is set.
    self::assertSame('Initial state', $instance->getRevisionLogMessage());
    self::assertIsInt($instance->getHash());
    self::assertIsInt($instance->getRevisionCreationTime());

    // Verify current state.
    self::assertEquals($testData, $instance->getSources());
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));
  }

  /**
   * Tests ::isPublishedPresent() reflects publish state correctly.
   */
  public function testIsPublishedPresent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $state1 = $this->makeSource('state_1');
    $state2 = $this->makeSource('state_2');

    // With no state and no published data, both hashes are NULL: matches.
    self::assertFalse($instance->isPublished());
    self::assertTrue($instance->isPublishedPresent());

    // After setting a state but before publishing, present hash diverges.
    $instance->setNewPresent($state1, 'State 1');
    self::assertFalse($instance->isPublished());
    self::assertFalse($instance->isPublishedPresent());

    // After publishing, present matches published.
    $instance->publish();
    self::assertTrue($instance->isPublished());
    self::assertTrue($instance->isPublishedPresent());

    // After a new state, present diverges from published again.
    $instance->setNewPresent($state2, 'State 2');
    self::assertTrue($instance->isPublished());
    self::assertFalse($instance->isPublishedPresent());
  }

  /**
   * Test the ::undo() method.
   */
  public function testUndo(): void {
    $instance = $this->createDisplayBuilderInstance();
    self::assertEmpty($instance->getSources());

    // Create multiple states.
    $state1 = $this->makeSource('state_1');
    $state2 = $this->makeSource('state_2');
    $state3 = $this->makeSource('state_3');

    $instance->setNewPresent($state1, 'State 1');
    $instance->setNewPresent($state2, 'State 2');
    $instance->setNewPresent($state3, 'State 3');

    // Verify we're at state 3.
    self::assertEquals($state3, $instance->getSources());
    self::assertSame(2, \count($instance->getPast()));
    self::assertSame(0, \count($instance->getFuture()));

    // Undo once.
    $instance = $this->storage->undo($instance);
    self::assertEquals($state2, $instance->getSources());
    self::assertSame(1, \count($instance->getPast()));
    self::assertSame(1, \count($instance->getFuture()));

    // Undo again, to beginning.
    $instance = $this->storage->undo($instance);
    self::assertEquals($state1, $instance->getSources());
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(2, \count($instance->getFuture()));

    // Try to undo when at beginning.
    $instance = $this->storage->undo($instance);
    self::assertEquals($state1, $instance->getSources());
    self::assertSame(0, \count($instance->getPast()));
    self::assertSame(2, \count($instance->getFuture()));
  }

  /**
   * Build a minimal source node array matching the ui_patterns field structure.
   *
   * @param string $value
   *   The source value.
   *
   * @return array
   *   A single-element source list.
   */
  private function makeSource(string $value): array {
    return [
      [
        'source_id' => 'textfield',
        'node_id' => \bin2hex(\random_bytes(8)),
        'source' => ['value' => $value],
        'third_party_settings' => [],
      ],
    ];
  }

}
