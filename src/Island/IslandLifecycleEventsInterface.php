<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\InstanceInterface;

/**
 * Island events for builder state lifecycle changes.
 *
 * Implement this interface to receive events when the builder history pointer
 * changes, the state is restored to the last save, or an entity view override
 * is reverted to the base display config.
 *
 * Covered events: ON_HISTORY_CHANGE, ON_RESTORE, ON_REVERT.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 * @see \Drupal\display_builder\Island\IslandLifecycleReloadTrait
 */
interface IslandLifecycleEventsInterface {

  /**
   * Event triggered when the history pointer changes (undo / redo).
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onHistoryChange(InstanceInterface $instance): array;

  /**
   * Event triggered when the builder state is restored to its last saved state.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onRestore(InstanceInterface $instance): array;

  /**
   * Event triggered when an entity override is reverted to the base config.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onRevert(InstanceInterface $instance): array;

}
