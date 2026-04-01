<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

/**
 * Aggregate interface for all island event groups.
 *
 * This interface extends all four event sub-interfaces and serves two purposes:
 *
 * 1. Backward compatibility — existing code that type-hints against
 *    IslandEventSubscriberInterface or IslandInterface continues to work
 *    without changes.
 *
 * 2. Full-coverage contract — IslandPluginBase implements this aggregate,
 *    providing no-op defaults for every event method.  Island plugins may
 *    implement only the specific sub-interfaces they need instead of the full
 *    set.
 *
 * Sub-interfaces by concern:
 * - IslandStructureEventsInterface — onAttachToRoot, onAttachToSlot, onMove,
 *   onUpdate, onDelete
 * - IslandLifecycleEventsInterface — onHistoryChange, onRestore, onRevert
 * - IslandSaveEventsInterface      — onPublish, onPresetSave
 * - IslandActiveEventInterface     — onActive
 *
 * @see \Drupal\display_builder\Island\IslandStructureEventsInterface
 * @see \Drupal\display_builder\Island\IslandLifecycleEventsInterface
 * @see \Drupal\display_builder\Island\IslandSaveEventsInterface
 * @see \Drupal\display_builder\Island\IslandActiveEventInterface
 * @see \Drupal\display_builder\Island\IslandFanOutTrait
 */
interface IslandEventSubscriberInterface extends
  IslandActiveEventInterface,
  IslandLifecycleEventsInterface,
  IslandSaveEventsInterface,
  IslandStructureEventsInterface {

}
