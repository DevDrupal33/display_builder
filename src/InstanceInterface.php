<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\display_builder\Entity\HistoryInterface;
use Drupal\display_builder\Entity\ProfileInterface;

/**
 * Provides an interface defining a display builder instance entity type.
 */
interface InstanceInterface extends ContentEntityInterface, ContextProviderInterface, HistoryInterface, PublishableInterface, RevisionLogInterface {

  /**
   * Returns the display builder profile.
   *
   * @return \Drupal\display_builder\Entity\ProfileInterface|null
   *   The display builder profile.
   */
  public function getProfile(): ?ProfileInterface;

  /**
   * Move a source to root.
   *
   * @param string $node_id
   *   The node ID of the source.
   * @param int $position
   *   The position.
   *
   * @return bool
   *   True if success, false otherwise.
   */
  public function moveToRoot(string $node_id, int $position): bool;

  /**
   * Attach a new source to root.
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
   *   The node ID of the source.
   */
  public function attachToRoot(int $position, string $source_id, array $data, array $third_party_settings = []): string;

  /**
   * Attach a new source to a slot.
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
   *   The node ID of the source.
   */
  public function attachToSlot(string $parent_id, string $slot_id, int $position, string $source_id, array $data, array $third_party_settings = []): string;

  /**
   * Move a source to a slot.
   *
   * @param string $node_id
   *   The node ID of the source.
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
  public function moveToSlot(string $node_id, string $parent_id, string $slot_id, int $position): bool;

  /**
   * Get node data.
   *
   * @param string $node_id
   *   The node ID of the source.
   *
   * @return array
   *   The node data.
   */
  public function getNode(string $node_id): array;

  /**
   * Get the parent id of an node.
   *
   * @param string $node_id
   *   The node ID of the source.
   *
   * @return string
   *   The parent node id. Empty if the node is at the root level.
   */
  public function getParentId(string $node_id): ?string;

  /**
   * Set the source for a tree node.
   *
   * @param string $node_id
   *   The node ID of the source.
   * @param string $source_id
   *   The source id.
   * @param array $data
   *   The source data.
   */
  public function setSource(string $node_id, string $source_id, array $data): void;

  /**
   * Set the third party settings for a tree node.
   *
   * @param string $node_id
   *   The node ID of the source.
   * @param string $island_id
   *   The island id (relative to third party settings).
   * @param array $data
   *   The third party data for the island.
   */
  public function setThirdPartySettings(string $node_id, string $island_id, array $data): void;

  /**
   * Remove an tree node.
   *
   * @param string $node_id
   *   The node ID of the source.
   */
  public function remove(string $node_id): void;

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
   * Get the path index.
   *
   * @return array
   *   The path index.
   */
  public function getPathIndex(): array;

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

  /**
   * Get saved hash of the current data.
   *
   * @return int
   *   The uniq id value.
   */
  public function getHash(): ?int;

}
