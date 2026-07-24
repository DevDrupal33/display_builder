<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\Event\DisplayBuilderEventsSubscriber;
use Drupal\display_builder\InstanceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the island event dispatch pipeline end-to-end.
 *
 * Verifies that events dispatched via the event_dispatcher reach enabled
 * island plugins, collect results, and respect the current_island_id skip
 * logic.
 *
 * @internal
 */
#[CoversClass(DisplayBuilderEventsSubscriber::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class IslandEventTest extends DisplayBuilderKernelTestBase {

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder', 'display_builder_test']);
  }

  /**
   * Tests the onAttachToRoot event is dispatched to enabled islands.
   */
  public function testOnAttachToRootDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onAttachToSlot event is dispatched to enabled islands.
   */
  public function testOnAttachToSlotDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1', 'parent_1');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_SLOT, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onMove event is dispatched to enabled islands.
   */
  public function testOnMoveDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_MOVE, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onDelete event is dispatched to enabled islands.
   */
  public function testOnDeleteDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, NULL, 'parent_1');
    $this->dispatch(DisplayBuilderEvents::ON_DELETE, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onHistoryChange event is dispatched to enabled islands.
   */
  public function testOnHistoryChangeDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance);
    $this->dispatch(DisplayBuilderEvents::ON_HISTORY_CHANGE, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onUpdate event is dispatched to enabled islands.
   */
  public function testOnUpdateDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_UPDATE, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests that the island matching current_island_id is skipped.
   */
  public function testCurrentIslandIdIsSkipped(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1', NULL, 'test_index_raw');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    self::assertArrayNotHasKey('test_index_raw', $event->getResult());
  }

  /**
   * Tests that a hidden View panel is skipped when the client reports.
   */
  public function testHiddenIslandIsDeferred(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    // The client reports it can see nothing deferrable.
    $event = new DisplayBuilderEvent($instance, NULL, 'node_1', NULL, NULL, []);
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    self::assertArrayNotHasKey('test_index_raw', $event->getResult());
  }

  /**
   * Tests that a View panel the client can see is still rendered.
   */
  public function testVisibleIslandIsNotDeferred(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1', NULL, NULL, ['test_index_raw']);
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests that no report from the client means every island is rendered.
   *
   * This is the path taken by server-sent events, functional tests and any
   * non-JS caller, and it must keep behaving as it did before deferral.
   */
  public function testUnreportedVisibilityRendersEveryIsland(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1', NULL, NULL, NULL);
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests that islands outside the deferrable set are always rendered.
   *
   * Toolbar buttons are permanently on screen, so they must be rebuilt even
   * when the client reports nothing visible.
   */
  public function testNonDeferrableIslandIsNeverDeferred(): void {
    $profile = $this->createDisplayBuilderProfile($this->randomMachineName());
    $profile->setIslandConfiguration('test_index_raw', ['status' => TRUE]);
    $profile->setIslandConfiguration('history', ['status' => TRUE]);
    $profile->save();

    $instance = $this->createDisplayBuilderInstance($profile->id());

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1', NULL, NULL, []);
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    $result = $event->getResult();
    // The View panel is deferred, the Button island beside it is not.
    self::assertArrayNotHasKey('test_index_raw', $result);
    self::assertArrayHasKey('history', $result);
  }

  /**
   * Tests that an island which declines deferral is always rendered.
   *
   * The Libraries panel's content is assembled by ProfileViewBuilder rather
   * than by its own build(), so reloading it on its own empties it. It must
   * therefore never be deferred, whatever the client reports.
   *
   * @see \Drupal\display_builder\Plugin\display_builder\Island\LibrariesPanel::isDeferrable()
   */
  public function testIslandDecliningDeferralIsNeverDeferred(): void {
    $island = $this->container->get('plugin.manager.db_island')
      ->createInstance('library', []);

    self::assertFalse($island->isDeferrable());

    // A View island that does not decline is still deferrable, so the guard is
    // specific to the panel rather than disabling deferral for View islands.
    $deferrable = $this->container->get('plugin.manager.db_island')
      ->createInstance('test_index_raw', []);

    self::assertTrue($deferrable->isDeferrable());
  }

  /**
   * Tests that a disabled island receives no event.
   */
  public function testDisabledIslandReceivesNoEvent(): void {
    $profile = $this->createDisplayBuilderProfile($this->randomMachineName());
    $profile->save();

    $instance = $this->createDisplayBuilderInstance($profile->id());

    $event = new DisplayBuilderEvent($instance, NULL, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    self::assertArrayNotHasKey('test_index_raw', $event->getResult());
  }

  /**
   * Dispatches an event via the Drupal event dispatcher service.
   *
   * @param string $event_name
   *   The event name constant from DisplayBuilderEvents.
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  private function dispatch(string $event_name, DisplayBuilderEvent $event): void {
    $this->container->get('event_dispatcher')->dispatch($event, $event_name);
  }

  /**
   * Creates a saved instance whose profile has the given island enabled.
   *
   * @param string $island_id
   *   The island plugin ID to enable on the profile.
   *
   * @return \Drupal\display_builder\InstanceInterface
   *   The display builder instance.
   */
  private function createInstanceWithIsland(string $island_id): InstanceInterface {
    $profile = $this->createDisplayBuilderProfile($this->randomMachineName());
    $profile->setIslandConfiguration($island_id, ['status' => TRUE]);
    $profile->save();

    return $this->createDisplayBuilderInstance($profile->id());
  }

  /**
   * Asserts that an island result contains an out-of-band wrapper.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The dispatched event.
   * @param string $island_id
   *   The island plugin ID to check in results.
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The instance used to derive the expected HTML ID.
   */
  private function assertOutOfBandResult(DisplayBuilderEvent $event, string $island_id, InstanceInterface $instance): void {
    $result = $event->getResult();

    self::assertArrayHasKey($island_id, $result);

    $oob = $result[$island_id];
    self::assertSame('html_tag', $oob['#type']);
    self::assertSame('div', $oob['#tag']);

    $expected_selector = \sprintf('innerHTML:#island-%s-%s', $instance->id(), $island_id);
    self::assertSame($expected_selector, (string) $oob['#attributes']['data-hx-swap-oob']);
    self::assertArrayHasKey('content', $oob);
  }

}
