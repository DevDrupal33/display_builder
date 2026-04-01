<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\InstanceInterface;

/**
 * Event carrying a required node ID and a required parent slot ID.
 *
 * Dispatched for ON_ATTACH_TO_SLOT, where both the inserted node and the
 * slot-owning parent are always known.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 */
final class DisplayBuilderSlotEvent extends DisplayBuilderNodeEvent {

  /**
   * Constructs a DisplayBuilderSlotEvent.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string $nodeId
   *   The tree node ID of the node being inserted.
   * @param string $parentId
   *   The node ID of the slot-owning parent (never NULL for slot events).
   * @param string|null $current_island_id
   *   The island ID that triggered the action, if any.
   */
  public function __construct(
    InstanceInterface $instance,
    string $nodeId,
    private string $parentId,
    ?string $current_island_id = NULL,
  ) {
    parent::__construct($instance, $nodeId, $current_island_id);
  }

  /**
   * Gets the parent node ID.
   *
   * @return string
   *   The parent node ID (guaranteed non-empty for slot events).
   */
  public function getParentId(): string {
    return $this->parentId;
  }

}
