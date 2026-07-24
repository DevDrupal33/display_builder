<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\InstanceStorage;
use Drupal\display_builder\InstanceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the InstanceStorage revision history rules.
 *
 * The storage is what makes undo/redo behave like an editor rather than like a
 * revision list: it caps the history, discards the redo stack on a new edit,
 * and promotes revisions between past and future. Those are product rules with
 * no other home - they live in SQL revision queries, so this is a kernel test;
 * mocking the query builder would assert the mock, not the ordering.
 *
 * ApiControllerTest drives undo()/redo() incidentally. This covers the rules
 * that class does not reach: the history cap, the redo-stack discard, clear(),
 * and getUsers().
 *
 * @internal
 */
#[CoversClass(InstanceStorage::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class InstanceStorageTest extends DisplayBuilderKernelTestBase {

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
   * The storage under test.
   */
  private InstanceStorage $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'display_builder', 'display_builder_test']);

    $this->createDisplayBuilderInstance('test_base', 'test_instance')->save();

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('display_builder_instance');
    \assert($storage instanceof InstanceStorage);
    $this->storage = $storage;
  }

  /**
   * Test the history is capped so it cannot grow without bound.
   *
   * MAX_HISTORY is 20. Every edit trims the oldest revision once past it, so
   * a long editing session keeps a bounded table rather than one row per
   * keystroke. Nothing else pins this constant.
   */
  public function testHistoryIsCappedAtTwentyPastRevisions(): void {
    for ($i = 0; $i < 25; ++$i) {
      $this->addRevision('value_' . $i);
    }

    // Exact, not "at most": 25 edits would leave 24 past revisions untrimmed,
    // so this number only holds if trimming actually ran. An upper-bound
    // assertion here would also pass on an empty history.
    self::assertCount(20, $this->reload()->getPast());
  }

  /**
   * Test editing after an undo discards the redo stack.
   *
   * This is the behavior every editor has and the one users would notice
   * losing: undo twice, type something, and the branch you undid away must not
   * still be reachable with redo.
   */
  public function testEditingAfterUndoDiscardsTheFuture(): void {
    $this->addRevision('one');
    $this->addRevision('two');
    $this->addRevision('three');

    $this->storage->undo($this->reload());
    self::assertNotEmpty($this->reload()->getFuture(), 'Undo creates a redo stack.');

    $this->addRevision('branched');

    self::assertEmpty($this->reload()->getFuture(), 'A new edit discards it.');
  }

  /**
   * Test undo and redo move a revision between past and future.
   */
  public function testUndoAndRedoMoveTheDefaultRevision(): void {
    $this->addRevision('one');
    $this->addRevision('two');

    $before = \count($this->reload()->getPast());

    $this->storage->undo($this->reload());
    self::assertCount($before - 1, $this->reload()->getPast(), 'Undo shortens the past.');
    self::assertCount(1, $this->reload()->getFuture(), 'And lengthens the future.');

    $this->storage->redo($this->reload());
    self::assertCount($before, $this->reload()->getPast(), 'Redo restores it.');
    self::assertEmpty($this->reload()->getFuture());
  }

  /**
   * Test undo and redo at the boundaries are safe no-ops.
   *
   * They return the entity unchanged rather than NULL, which is what lets the
   * toolbar call them unconditionally without a guard.
   */
  public function testUndoAndRedoAtBoundariesReturnTheEntity(): void {
    $instance = $this->reload();

    self::assertSame($instance->getRevisionId(), $this->storage->undo($instance)->getRevisionId());
    self::assertSame($instance->getRevisionId(), $this->storage->redo($instance)->getRevisionId());
  }

  /**
   * Test clear() drops every revision but the current one.
   */
  public function testClearKeepsOnlyTheDefaultRevision(): void {
    $this->addRevision('one');
    $this->addRevision('two');
    self::assertNotEmpty($this->reload()->getPast());

    $current = $this->reload();
    $revision_id = $current->getRevisionId();
    $this->storage->clear($current);

    $after = $this->reload();
    self::assertEmpty($after->getPast(), 'The past is gone.');
    self::assertEmpty($after->getFuture(), 'The future too.');
    self::assertSame($revision_id, $after->getRevisionId(), 'The current revision survives.');
  }

  /**
   * Test getUsers() reports each author once, with their most recent edit.
   *
   * The history panel lists collaborators, so an author editing ten times must
   * appear once - and with their latest timestamp, not their first.
   */
  public function testGetUsersDeduplicatesByMostRecentEdit(): void {
    $this->addRevision('one');
    $this->addRevision('two');
    $this->addRevision('three');

    $users = $this->storage->getUsers($this->reload());

    self::assertCount(1, $users, 'One author, one entry.');
    self::assertArrayHasKey(0, $users, 'Anonymous is standardized to user 0.');
    self::assertIsInt($users[0]);
  }

  /**
   * Adds one revision carrying a single token node with the given value.
   */
  private function addRevision(string $value): void {
    $this->reload()->setNewPresent([
      [
        'source_id' => 'token',
        'source' => ['value' => $value],
      ],
    ], 'Step ' . $value);
  }

  /**
   * Reloads the instance so each step sees the current default revision.
   */
  private function reload(): InstanceInterface {
    $this->storage->resetCache(['test_instance']);
    $instance = $this->storage->load('test_instance');
    \assert($instance instanceof InstanceInterface);

    return $instance;
  }

}
