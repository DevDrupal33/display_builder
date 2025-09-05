<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface defining a display builder instance entity type.
 */
interface InstanceInterface extends EntityInterface, HistoryInterface {

  /**
   * Returns the display builder profile.
   *
   * @return \Drupal\display_builder\DisplayBuilderInterface|null
   *   The display builder profile.
   */
  public function getProfile(): ?DisplayBuilderInterface;

  /**
   * Set the display builder profile from id.
   *
   * @param string $profile_id
   *   Entity ID of the display builder profile.
   */
  public function setProfile(string $profile_id): void;

  /**
   * Move an instance to root.
   *
   * @param string $instance_id
   *   The instance id.
   * @param int $position
   *   The position.
   *
   * @return bool
   *   True if success, false otherwise.
   */
  public function moveToRoot(string $instance_id, int $position): bool;

  /**
   * Attach a new source instance to root.
   *
   * @param int $position
   *   The position.
   * @param string $source_id
   *   The source ID.
   * @param array $data
   *   The source data.
   * @param array $third_party_settings
   *   (Optional) The source third party settings. Used for paste/duplicate.
   *
   * @return string
   *   The instance ID of the new component.
   */
  public function attachSourceToRoot(int $position, string $source_id, array $data, array $third_party_settings = []): string;

  /**
   * Attach a new source instance to a slot.
   *
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
   * @param array $third_party_settings
   *   (Optional) The source third party settings. Used for paste/duplicate.
   *
   * @return string
   *   The instance ID of the new component.
   */
  public function attachSourceToSlot(string $parent_id, string $slot_id, int $position, string $source_id, array $data, array $third_party_settings = []): string;

  /**
   * Move an instance to a slot.
   *
   * @param string $instance_id
   *   The instance id.
   * @param string $parent_id
   *   The parent id.
   * @param string $slot_id
   *   The slot id.
   * @param int $position
   *   The position.
   *
   * @return bool
   *   True if success, false otherwise.
   */
  public function moveToSlot(string $instance_id, string $parent_id, string $slot_id, int $position): bool;

  /**
   * Get instance data.
   *
   * @param string $instance_id
   *   The instance id.
   *
   * @return array
   *   The instance data.
   */
  public function get(string $instance_id): array;

  /**
   * Get the parent id of an instance.
   *
   * @param array $root
   *   The root data.
   * @param string $instance_id
   *   The instance id.
   *
   * @return string
   *   The parent id or empty.
   */
  public function getParentId(array $root, string $instance_id): string;

  /**
   * Set the source for an instance.
   *
   * @param string $instance_id
   *   The instance id.
   * @param string $source_id
   *   The source id.
   * @param array $data
   *   The source data.
   */
  public function setSource(string $instance_id, string $source_id, array $data): void;

  /**
   * Set the third party settings for an instance.
   *
   * @param string $instance_id
   *   The instance id.
   * @param string $island_id
   *   The island id (relative to third party settings).
   * @param array $data
   *   The third party data for the island.
   */
  public function setThirdPartySettings(string $instance_id, string $island_id, array $data): void;

  /**
   * Remove an instance.
   *
   * @param string $instance_id
   *   The instance id.
   */
  public function remove(string $instance_id): void;

  /**
   * Set the save value of a display builder.
   *
   * @param array $save_data
   *   The builder data to save.
   */
  public function setSave(array $save_data): void;

  /**
   * Gets the values for all defined contexts.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]|null
   *   An array of set contexts, keyed by context name.
   */
  public function getContexts(): ?array;

  /**
   * Get users.
   *
   * All users which have authored a step in present, past or future, with the
   * most recent date of action.
   *
   * @return array
   *   Each key is an User entity ID, each value is a timestamp.
   */
  public function getUsers(): array;

  /**
   * Check display has required context, meaning it can save value.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[]|null $contexts
   *   (Optional) contexts if already accessible, keyed by context name.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function canSaveContextsRequirement(?array $contexts = NULL): bool;

  /**
   * Check display has required context, meaning it can save value.
   *
   * @param string $key
   *   The context key to look for.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   (Optional) contexts if already accessible, keyed by context name.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function hasSaveContextsRequirement(string $key, array $contexts = []): bool;

  /**
   * If display builder has been saved.
   *
   * @return bool
   *   Has save data.
   */
  public function hasSave(): bool;

  /**
   * The save value is the current value of display builder.
   *
   * @return bool
   *   The save is the current or not.
   */
  public function saveIsCurrent(): bool;

  /**
   * Get the path index.
   *
   * @param array $root
   *   (Optional) The root state.
   *
   * @return array
   *   The path index.
   */
  public function getPathIndex(array $root = []): array;

  /**
   * Restore to the last saved state.
   */
  public function restore(): void;

  /**
   * Get a hash for this data as uniq id reference.
   *
   * @param array $data
   *   The data to generate uniq id for.
   *
   * @return int
   *   The uniq id value.
   */
  public static function getUniqId(array $data): int;

}
