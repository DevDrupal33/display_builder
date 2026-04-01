<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\InstanceInterface;

/**
 * Event carrying a required tree node ID.
 *
 * Dispatched for events that operate on a single node:
 * ON_ATTACH_TO_ROOT, ON_MOVE, ON_UPDATE.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 */
class DisplayBuilderNodeEvent extends DisplayBuilderEvent {

  /**
   * Constructs a DisplayBuilderNodeEvent.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string $nodeId
   *   The tree node ID (always required for node-scoped events).
   * @param string|null $current_island_id
   *   The island ID that triggered the action, if any.
   */
  public function __construct(
    InstanceInterface $instance,
    private string $nodeId,
    ?string $current_island_id = NULL,
  ) {
    parent::__construct($instance, $current_island_id);
  }

  /**
   * Gets the tree node ID.
   *
   * @return string
   *   The node ID (guaranteed non-empty for this event type).
   */
  public function getNodeId(): string {
    return $this->nodeId;
  }

}
