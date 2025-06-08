<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

/**
 * Provides an interface for data actions.
 */
interface DataSateInterface {

  /**
   * Load a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array|null
   *   The display builder or null if do not exist.
   */
  public function load(string $builder_id): ?array;

  /**
   * Load all display builders.
   *
   * @return array
   *   The list of display builders.
   */
  public function loadAll(): array;

  /**
   * Delete a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function delete(string $builder_id): void;

  /**
   * Delete all display builders.
   */
  public function deleteAll(): void;

  /**
   * If display builder has been saved.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return bool
   *   Has save data.
   */
  public function hasSave(string $builder_id): bool;

  /**
   * The save value is the current value of display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return bool
   *   The save is the current or not.
   */
  public function saveIsCurrent(string $builder_id): bool;

  /**
   * Set the save value of a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $save_data
   *   The builder data to save.
   */
  public function setSave(string $builder_id, array $save_data): void;

  /**
   * Get current hash.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return string
   *   The current hash.
   */
  public function getCurrentHash(string $builder_id): string;

}
