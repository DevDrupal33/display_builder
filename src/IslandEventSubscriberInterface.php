<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for island event subscriber.
 */
interface IslandEventSubscriberInterface {

  /**
   * Event triggered when a block becomes active.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $data
   *   The block data.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onActive(string $builder_id, array $data): array;

  /**
   * Event triggered when a block is attached to the root.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array;

  /**
   * Event triggered when a block is attached to a slot.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   * @param string $parent_id
   *   The parent block instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array;

  /**
   * Event triggered when a block is deleted.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $parent_id
   *   The parent block instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onDelete(string $builder_id, string $parent_id): array;

  /**
   * Event triggered when the history changes.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onHistoryChange(string $builder_id): array;

  /**
   * Event triggered when a block is moved.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onMove(string $builder_id, string $instance_id): array;

  /**
   * Event triggered when a block is updated.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   * @param string|null $current_island_id
   *   The current island ID which trigger action.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onUpdate(string $builder_id, string $instance_id, ?string $current_island_id): array;

  /**
   * Event triggered when a builder is saved.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onSave(string $builder_id): array;

  /**
   * Event triggered when a preset is saved.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  public function onPresetSave(string $builder_id): array;

}
