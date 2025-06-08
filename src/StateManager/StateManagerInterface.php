<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for display builder manager.
 */
interface StateManagerInterface extends ContextAwareInterface, DataSateInterface, SaveContextInterface {

  /**
   * Attach a new source instance to root.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param int $position
   *   The position.
   * @param string $source_id
   *   The source ID.
   * @param array $data
   *   The source data.
   *
   * @return string
   *   The instance ID of the new component.
   */
  public function attachSourceToRoot(string $builder_id, int $position, string $source_id, array $data): string;

  /**
   * Attach a new source instance a slot.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $parent_id
   *   The parent id.
   * @param string $slot_id
   *   The slot id.
   * @param int $position
   *   The position.
   * @param string $source_id
   *   The source ID.
   * @param array $data
   *   The source data.
   */
  public function attachSourceToSlot(string $builder_id, string $parent_id, string $slot_id, int $position, string $source_id, array $data): string;

  /**
   * Create a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $entity_config_id
   *   The display builder entity config id.
   * @param array|null $builder_data
   *   The populated data or current if null.
   * @param array $contexts
   *   The contexts for this builder_id.
   *
   * @return array
   *   The display builder ready.
   */
  public function create(string $builder_id, string $entity_config_id, ?array $builder_data, array $contexts): array;

  /**
   * Get instance data.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $instance_id
   *   The instance id.
   *
   * @return array
   *   The instance data.
   */
  public function get(string $builder_id, string $instance_id): array;

  /**
   * Get the current step.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array
   *   The current state.
   */
  public function getCurrent(string $builder_id): array;

  /**
   * Get the state of the current step.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array
   *   The current state.
   */
  public function getCurrentState(string $builder_id): array;

  /**
   * Get the entity config id.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return string
   *   The entity config id.
   */
  public function getEntityConfigId(string $builder_id): string;

  /**
   * Get the parent id of an instance.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $root
   *   The root data.
   * @param string $instance_id
   *   The instance id.
   *
   * @return string
   *   The parent id or empty.
   */
  public function getParentId(string $builder_id, array $root, string $instance_id): string;

  /**
   * Get the path index.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $root
   *   (Optional) The root state.
   *
   * @return array
   *   The path index.
   */
  public function getPathIndex(string $builder_id, array $root = []): array;

  /**
   * Move an instance to root.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $instance_id
   *   The instance id.
   * @param int $position
   *   The position.
   */
  public function moveToRoot(string $builder_id, string $instance_id, int $position): void;

  /**
   * Move an instance to a slot.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $instance_id
   *   The instance id.
   * @param string $parent_id
   *   The parent id.
   * @param string $slot_id
   *   The slot id.
   * @param int $position
   *   The position.
   */
  public function moveToSlot(string $builder_id, string $instance_id, string $parent_id, string $slot_id, int $position): void;

  /**
   * Move history to the first future state.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function redo(string $builder_id): void;

  /**
   * Remove an instance.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $instance_id
   *   The instance id.
   */
  public function remove(string $builder_id, string $instance_id): void;

  /**
   * Reset history to the current state.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function clear(string $builder_id): void;

  /**
   * Restore to the last saved state.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function restore(string $builder_id): void;

  /**
   * Save a display builder state.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $builder_data
   *   The display builder data.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $log_message
   *   (Optional) The log message.
   */
  public function save(string $builder_id, array $builder_data, string|TranslatableMarkup $log_message = ''): void;

  /**
   * Set the entity config id for an instance.
   *
   * @param string $builder_id
   *   The builder id.
   * @param string $entity_config_id
   *   The config id.
   */
  public function setEntityConfigId(string $builder_id, string $entity_config_id): void;

  /**
   * Set the source for an instance.
   *
   * @param string $builder_id
   *   The builder id.
   * @param string $instance_id
   *   The instance id.
   * @param string $source_id
   *   The source id.
   * @param array $data
   *   The source data.
   */
  public function setSource(string $builder_id, string $instance_id, string $source_id, array $data): void;

  /**
   * Set the third party settings for an instance.
   *
   * @param string $builder_id
   *   The builder id.
   * @param string $instance_id
   *   The instance id.
   * @param string $island_id
   *   The island id (relative to third party settings).
   * @param array $data
   *   The third party data for the island.
   */
  public function setThirdPartySettings(string $builder_id, string $instance_id, string $island_id, array $data): void;

  /**
   * Move history to the last past state.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function undo(string $builder_id): void;

  /**
   * Get number of past logs.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return int
   *   The number of past logs.
   */
  public function getCountPast(string $builder_id): int;

  /**
   * Get number of future logs.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return int
   *   The number of future logs.
   */
  public function getCountFuture(string $builder_id): int;

}
