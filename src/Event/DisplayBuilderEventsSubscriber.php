<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\IslandPluginManagerInterface;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected IslandPluginManagerInterface $islandManager,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      DisplayBuilderEvents::ON_CONTENT_UPDATE => 'onContentUpdate',
    ];
  }

  /**
   * Event handler for when a block becomes active.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onActive(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getData()]);
  }

  /**
   * Event handler for when a block is attached to the root.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onAttachToRoot(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getInstanceId()]);
  }

  /**
   * Event handler for when a block is attached to a slot.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onAttachToSlot(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getInstanceId(), $event->getParentId()]);
  }

  /**
   * Event handler for when a block is deleted.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onDelete(DisplayBuilderEvent $event): void {
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
   * Event handler for when a block is moved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onMove(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getInstanceId()]);
  }

  /**
   * Event handler for when a block is updated.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onUpdate(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getInstanceId(), $event->getCurrentIslandId()]);
  }

  /**
   * Event handler for when a display is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onSave(DisplayBuilderEvent $event): void {
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

  /**
   * Event handler for when a preset is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onContentUpdate(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__);
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
  private function dispatchToIslands(DisplayBuilderEvent $event, string $method, array $parameters = []): void {
    \array_unshift($parameters, $event->getBuilderId());

    $configuration = $event->getIslandConfiguration();
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($event->getBuilderId());
    $contexts = $builder->getContexts();
    $islands = $this->islandManager->createInstances($this->islandManager->getDefinitions(), $contexts, $configuration);

    $island_enabled = $event->getIslandEnabled();

    foreach ($islands as $island_id => $island) {
      if (!isset($island_enabled[$island_id])) {
        continue;
      }

      // Skip the island triggering the HTMX event. Useful to avoid swapping
      // the content of an island which is already in the expected state.
      // For examples, if we move an instance in Builder, Layers or Tree
      // panels, if we change the settings in InstanceForm.
      // @see Drupal\display_builder\Controller\ApiControllerBase::islandId
      if ($island_id === $event->getCurrentIslandId()) {
        continue;
      }

      if (!\method_exists($island, $method)) {
        continue;
      }
      $result = $island->{$method}(...$parameters);

      if ($result !== NULL) {
        $event->appendResult($island_id, $result);
      }
    }
  }

}
