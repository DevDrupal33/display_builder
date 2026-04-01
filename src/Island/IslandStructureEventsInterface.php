<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\InstanceInterface;

/**
 * Island events for structural tree mutations.
 *
 * Implement this interface to receive events when nodes are added, moved,
 * updated, or deleted from the builder tree.
 *
 * Covered events: ON_ATTACH_TO_ROOT, ON_ATTACH_TO_SLOT, ON_MOVE, ON_UPDATE,
 * ON_DELETE.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 * @see \Drupal\display_builder\Island\IslandStructureReloadTrait
 */
interface IslandStructureEventsInterface {

  /**
   * Event triggered when a node is attached to the root.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array;

  /**
   * Event triggered when a node is attached into a component slot.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   * @param string $node_id
   *   The tree node ID.
   * @param string $parent_id
   *   The parent node ID.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array;

  /**
   * Event triggered when an existing node is moved.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onMove(InstanceInterface $instance, string $node_id): array;

  /**
   * Event triggered when a node's source configuration is updated in-place.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array;

  /**
   * Event triggered when a node is removed from the tree.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   * @param string|null $parent_id
   *   The parent node ID, or NULL if the deleted node was at root level.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array;

}
