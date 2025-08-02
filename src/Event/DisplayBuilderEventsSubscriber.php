<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderEventsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new ApiController object.
   *
   * @param \Drupal\display_builder\IslandPluginManagerInterface $islandManager
   *   The island plugin manager.
   * @param \Drupal\display_builder\StateManager\StateManagerInterface $stateManager
   *   The state manager service.
   */
  public function __construct(
    protected IslandPluginManagerInterface $islandManager,
    protected StateManagerInterface $stateManager,
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
      DisplayBuilderEvents::ON_MOVE => 'onMove',
      DisplayBuilderEvents::ON_UPDATE => 'onUpdate',
      DisplayBuilderEvents::ON_SAVE => 'onSave',
      DisplayBuilderEvents::ON_PRESET_SAVE => 'onPresetSave',
    ];
  }

  /**
   * Event handler for when a block becomes active.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onActive(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getData()]);
  }

  /**
   * Event handler for when a block is attached to the root.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onAttachToRoot(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getInstanceId()]);
  }

  /**
   * Event handler for when a block is attached to a slot.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onAttachToSlot(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getInstanceId(), $event->getParentId()]);
  }

  /**
   * Event handler for when a block is deleted.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onDelete(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getParentId()]);
  }

  /**
   * Event handler for when the history changes.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onHistoryChange(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__);
  }

  /**
   * Event handler for when a block is moved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onMove(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getInstanceId()]);
  }

  /**
   * Event handler for when a block is updated.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onUpdate(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getInstanceId(), $event->getCurrentIslandId()]);
  }

  /**
   * Event handler for when a display is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onSave(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__, [$event->getData()]);
  }

  /**
   * Event handler for when a preset is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onPresetSave(DisplayBuilderEvent $event): void {
    $this->dispatch($event, __FUNCTION__);
  }

  /**
   * Dispatch the event with a generic code.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   * @param string $method
   *   The method to dispatch.
   * @param array $parameters
   *   (Optional) The parameters to the method.
   */
  private function dispatch(DisplayBuilderEvent $event, string $method, array $parameters = []): void {
    \array_unshift($parameters, $event->getBuilderId());

    $configuration = $event->getIslandConfiguration();
    $contexts = $this->stateManager->getContexts($event->getBuilderId());
    $islands = $this->islandManager->createInstances($this->islandManager->getDefinitions(), $contexts, $configuration);

    $island_enabled = $event->getIslandEnabled();

    foreach ($islands as $island_id => $island) {
      if (!isset($island_enabled[$island_id])) {
        continue;
      }

      if (!\method_exists($island, $method)) {
        continue;
      }
      $result = $island->{$method}(...$parameters);

      if ($result !== NULL) {
        $event->appendResult($result);
      }
    }
  }

}
