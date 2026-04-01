<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Event\DisplayBuilderDeleteEvent;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\Event\DisplayBuilderEventsSubscriber;
use Drupal\display_builder\Event\DisplayBuilderNodeEvent;
use Drupal\display_builder\Event\DisplayBuilderSlotEvent;
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
    $this->installConfig(['display_builder']);
  }

  /**
   * Tests the onAttachToRoot event is dispatched to enabled islands.
   */
  public function testOnAttachToRootDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderNodeEvent($instance, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onAttachToSlot event is dispatched to enabled islands.
   */
  public function testOnAttachToSlotDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderSlotEvent($instance, 'node_1', 'parent_1');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_SLOT, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onMove event is dispatched to enabled islands.
   */
  public function testOnMoveDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderNodeEvent($instance, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_MOVE, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests the onDelete event is dispatched to enabled islands.
   */
  public function testOnDeleteDispatchesToEnabledIslands(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderDeleteEvent($instance, 'parent_1');
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

    $event = new DisplayBuilderNodeEvent($instance, 'node_1');
    $this->dispatch(DisplayBuilderEvents::ON_UPDATE, $event);

    $this->assertOutOfBandResult($event, 'test_index_raw', $instance);
  }

  /**
   * Tests that the island matching current_island_id is skipped.
   */
  public function testCurrentIslandIdIsSkipped(): void {
    $instance = $this->createInstanceWithIsland('test_index_raw');

    $event = new DisplayBuilderNodeEvent($instance, 'node_1', 'test_index_raw');
    $this->dispatch(DisplayBuilderEvents::ON_ATTACH_TO_ROOT, $event);

    self::assertArrayNotHasKey('test_index_raw', $event->getResult());
  }

  /**
   * Tests that a disabled island receives no event.
   */
  public function testDisabledIslandReceivesNoEvent(): void {
    $profile = $this->createDisplayBuilderProfile($this->randomMachineName());
    $profile->save();

    $instance = $this->createDisplayBuilderInstance($profile->id());

    $event = new DisplayBuilderNodeEvent($instance, 'node_1');
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
    self::assertSame($expected_selector, $oob['#attributes']['hx-swap-oob']);
    self::assertArrayHasKey('content', $oob);
  }

}
