<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\Island\IslandFanOutTrait;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for Display Builder islands.
 */
class DisplayBuilderEventsSubscriber implements EventSubscriberInterface {

  use IslandFanOutTrait;

  /**
   * Constructs a new DisplayBuilderEventsSubscriber object.
   */
  public function __construct(
    protected IslandPluginManagerInterface $islandManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DisplayBuilderEvents::ON_ACTIVE => 'onActive',
      DisplayBuilderEvents::ON_ATTACH_TO_ROOT => 'onAttachToRoot',
      DisplayBuilderEvents::ON_ATTACH_TO_SLOT => 'onAttachToSlot',
      DisplayBuilderEvents::ON_DELETE => 'onDelete',
      DisplayBuilderEvents::ON_HISTORY_CHANGE => 'onHistoryChange',
      DisplayBuilderEvents::ON_RESTORE => 'onRestore',
      DisplayBuilderEvents::ON_REVERT => 'onRevert',
      DisplayBuilderEvents::ON_MOVE => 'onMove',
      DisplayBuilderEvents::ON_UPDATE => 'onUpdate',
      DisplayBuilderEvents::ON_PUBLISH => 'onPublish',
      DisplayBuilderEvents::ON_PRESET_SAVE => 'onPresetSave',
    ];
  }

  /**
   * Event handler for when a block becomes active.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderDataEvent $event
   *   The event object.
   */
  public function onActive(DisplayBuilderDataEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getData()]);
  }

  /**
   * Event handler for when a block is attached to the root.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderNodeEvent $event
   *   The event object.
   */
  public function onAttachToRoot(DisplayBuilderNodeEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId()]);
  }

  /**
   * Event handler for when a block is attached to a slot.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderSlotEvent $event
   *   The event object.
   */
  public function onAttachToSlot(DisplayBuilderSlotEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId(), $event->getParentId()]);
  }

  /**
   * Event handler for when a block is deleted.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderDeleteEvent $event
   *   The event object.
   */
  public function onDelete(DisplayBuilderDeleteEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getParentId()]);
  }

  /**
   * Event handler for when the history changes.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onHistoryChange(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__);
  }

  /**
   * Event handler for when the builder is restored to its last saved state.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onRestore(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__);
  }

  /**
   * Event handler for when an override is reverted to its default state.
   *
   * Reuses the history-change island callbacks since the UI refresh is
   * identical: all islands must re-render the updated state and history.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onRevert(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__);
  }

  /**
   * Event handler for when a block is moved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderNodeEvent $event
   *   The event object.
   */
  public function onMove(DisplayBuilderNodeEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId()]);
  }

  /**
   * Event handler for when a block is updated.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderNodeEvent $event
   *   The event object.
   */
  public function onUpdate(DisplayBuilderNodeEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId()]);
  }

  /**
   * Event handler for when a display is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderDataEvent $event
   *   The event object.
   */
  public function onPublish(DisplayBuilderDataEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getData()]);
  }

  /**
   * Event handler for when a preset is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onPresetSave(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__);
  }

}
