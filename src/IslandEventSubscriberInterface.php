<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for Island event subscriber.
 */
interface IslandEventSubscriberInterface {

  /**
   * Event triggered when a node becomes active.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   * @param array $data
   *   The node data.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onActive(string $instance_id, array $data): array;

  /**
   * Event triggered when a node is attached to the root.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onAttachToRoot(string $instance_id, string $node_id): array;

  /**
   * Event triggered when a node is attached to a slot.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   * @param string $parent_id
   *   The parent node instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onAttachToSlot(string $instance_id, string $node_id, string $parent_id): array;

  /**
   * Event triggered when a node is deleted.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   * @param string $parent_id
   *   The parent node instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onDelete(string $instance_id, string $parent_id): array;

  /**
   * Event triggered when the history changes.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onHistoryChange(string $instance_id): array;

  /**
   * Event triggered when a node is moved.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onMove(string $instance_id, string $node_id): array;

  /**
   * Event triggered when a node is updated.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onUpdate(string $instance_id, string $node_id): array;

  /**
   * Event triggered when a builder is saved.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onSave(string $instance_id): array;

  /**
   * Event triggered when a preset is saved.
   *
   * @param string $instance_id
   *   The Display Builder instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onPresetSave(string $instance_id): array;

  /**
   * Event triggered when a source is locked or unlocked.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onLockSwitch(string $builder_id, string $instance_id): array;

}
