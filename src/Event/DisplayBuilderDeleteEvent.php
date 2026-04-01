<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\InstanceInterface;

/**
 * Event carrying an optional parent node ID.
 *
 * Dispatched for ON_DELETE. The parent ID is NULL when the deleted node
 * was at root level and therefore had no parent.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 */
final class DisplayBuilderDeleteEvent extends DisplayBuilderEvent {

  /**
   * Constructs a DisplayBuilderDeleteEvent.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string|null $parentId
   *   The parent node ID of the deleted node, or NULL if it was at root level.
   * @param string|null $current_island_id
   *   The island ID that triggered the action, if any.
   */
  public function __construct(
    InstanceInterface $instance,
    private ?string $parentId,
    ?string $current_island_id = NULL,
  ) {
    parent::__construct($instance, $current_island_id);
  }

  /**
   * Gets the parent node ID of the deleted node.
   *
   * @return string|null
   *   The parent node ID, or NULL if the node was at root level.
   */
  public function getParentId(): ?string {
    return $this->parentId;
  }

}
