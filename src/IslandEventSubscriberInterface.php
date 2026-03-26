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
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param array $data
   *   The node data.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onActive(InstanceInterface $instance, array $data): array;

  /**
   * Event triggered when a node is attached to the root.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array;

  /**
   * Event triggered when a node is attached to a slot.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   * @param string $parent_id
   *   The parent node instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array;

  /**
   * Event triggered when a node is deleted.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $parent_id
   *   The parent node instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onDelete(InstanceInterface $instance, string $parent_id): array;

  /**
   * Event triggered when the history changes.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onHistoryChange(InstanceInterface $instance): array;

  /**
   * Event triggered when a node is moved.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onMove(InstanceInterface $instance, string $node_id): array;

  /**
   * Event triggered when a node is updated.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array;

  /**
   * Event triggered when a builder is saved.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onSave(InstanceInterface $instance): array;

  /**
   * Event triggered when a preset is saved.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onPresetSave(InstanceInterface $instance): array;

}
