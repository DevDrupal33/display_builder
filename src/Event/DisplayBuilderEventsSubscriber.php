<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for Display Builder islands.
 */
class DisplayBuilderEventsSubscriber implements EventSubscriberInterface {

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
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId()]);
  }

  /**
   * Event handler for when a block is attached to a slot.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onAttachToSlot(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId(), $event->getParentId()]);
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
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onMove(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId()]);
  }

  /**
   * Event handler for when a block is updated.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onUpdate(DisplayBuilderEvent $event): void {
    $this->dispatchToIslands($event, __FUNCTION__, [$event->getNodeId()]);
  }

  /**
   * Event handler for when a display is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onPublish(DisplayBuilderEvent $event): void {
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
    \array_unshift($parameters, $event->getInstance());

    $configuration = $event->getIslandConfiguration();
    $contexts = $event->getInstance()->getAvailableContexts();
    $island_enabled = $event->getEnabledIslands();

    $definitions = \array_intersect_key($this->islandManager->getDefinitions(), $island_enabled);
    $islands = $this->islandManager->createInstances($definitions, $contexts, $configuration);

    $visible_islands = $event->getVisibleIslands();

    foreach ($islands as $island_id => $island) {
      if (!isset($island_enabled[$island_id])) {
        continue;
      }

      // Skip the island triggering the HTMX event. Useful to avoid swapping
      // the content of an island which is already in the expected state.
      // For examples, if we move an instance in Builder, Wireframe or Tree
      // panels, if we change the settings in InstanceForm.
      // @see Drupal\display_builder\Controller\ApiControllerBase::islandId
      if ($island_id === $event->getCurrentIslandId()) {
        continue;
      }

      // Skip panels the client told us are off screen. They are rebuilt on
      // demand when the user brings them back into view.
      if ($visible_islands !== NULL && $this->shouldDefer($island, $visible_islands)) {
        continue;
      }

      $result = $island->{$method}(...$parameters);

      if ($result !== NULL) {
        $event->appendResult($island_id, $result);
      }
    }
  }

  /**
   * Determines whether an island's rebuild can be deferred until it is shown.
   *
   * Only panels the user cannot currently see are worth deferring, and only
   * where the client is able to notice they went stale and ask for them again.
   * That is true of View panels in the tabbed main area and in the start
   * sidebar drawer - one visible at a time in each - and of the Floating
   * controls which are shown and hidden along with the panel they attach to.
   *
   * Everything else (toolbar buttons, contextual menu entries, Library and
   * Contextual tabs) is always rendered: it is either permanently on screen or
   * cheap enough that deferring it would cost more than it saves.
   *
   * Which islands are eligible at all is the island's own answer, so a panel
   * that cannot survive a standalone reload can decline. This method only
   * decides whether an eligible island is currently off screen.
   *
   * The region is deliberately not consulted: IslandType::regions() offers a
   * View island only 'main' and 'sidebar', both of which show one panel at a
   * time, so every View island qualifies. Testing the region would also be
   * wrong, since it is only stored on the profile when explicitly configured
   * (@see \Drupal\display_builder\Entity\Profile::setIslandConfiguration()) -
   * an island left at its default would fall through and never be deferred.
   *
   * @param \Drupal\display_builder\Island\IslandInterface $island
   *   The island to test.
   * @param array $visible_islands
   *   The island plugin IDs the client reports as currently visible.
   *
   * @return bool
   *   TRUE if this island's rebuild should be skipped for this request.
   *
   * @see \Drupal\display_builder\Island\IslandInterface::isDeferrable()
   * @see \Drupal\display_builder\Controller\ApiController::reloadIsland()
   */
  private function shouldDefer(IslandInterface $island, array $visible_islands): bool {
    if (!$island->isDeferrable()) {
      return FALSE;
    }

    $type = $island->getTypeId();

    // A View tab, or the Preview pane, is off screen exactly when the client
    // does not report its plugin ID among the visible panes.
    if ($type === IslandType::View->value || $type === IslandType::Preview->value) {
      return !\in_array($island->getPluginId(), $visible_islands, TRUE);
    }

    // A Floating island rides along with the panel(s) it is attached to, so it
    // is off screen exactly when all of them are.
    // @see \Drupal\display_builder\ProfileViewBuilder::buildFloatingControlsRegion()
    if ($type === IslandType::Floating->value) {
      $definition = $island->getPluginDefinition();
      $attach_to = \is_array($definition) ? ($definition['attach_to'] ?? []) : [];

      return $attach_to !== [] && \array_intersect($attach_to, $visible_islands) === [];
    }

    return FALSE;
  }

}
