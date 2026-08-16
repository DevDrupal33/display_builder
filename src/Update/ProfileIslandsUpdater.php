<?php

declare(strict_types=1);

namespace Drupal\display_builder\Update;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandType;

/**
 * Applies island configuration changes to every stored profile.
 *
 * Post update hooks keep doing the same four things to the profile 'islands'
 * map: rename an island, drop a key a plugin stopped reading, move a key to its
 * new name, seed a key with a value. The bookkeeping around them - load every
 * profile, remember which ones an operation really touched, save only those -
 * is the same every time and is what goes wrong. It lives here, so a hook reads
 * as the list of changes it makes.
 *
 * Operations apply in call order and are cumulative, so a rename can be
 * followed by a change addressing the new name.
 *
 * @code
 * ProfileIslandsUpdater::create()
 *   ->renameIsland('layers', 'scaffold')
 *   ->removeKey('format', ['viewport'])
 *   ->save();
 *
 * @endcode
 */
final class ProfileIslandsUpdater {

  /**
   * The profiles to update, keyed by profile ID.
   *
   * @var array<string, \Drupal\display_builder\Entity\ProfileInterface>
   */
  private array $profiles = [];

  /**
   * The islands map of each profile, keyed by profile ID.
   *
   * @var array<string, array<string, mixed>>
   */
  private array $islands = [];

  /**
   * The IDs of the profiles an operation actually changed.
   *
   * @var array<string, bool>
   */
  private array $changed = [];

  /**
   * Constructs a ProfileIslandsUpdater.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $storage = $entityTypeManager->getStorage('display_builder_profile');

    foreach ($storage->loadMultiple() as $id => $profile) {
      if (!$profile instanceof ProfileInterface) {
        continue;
      }

      $islands = $profile->get('islands');

      if (!\is_array($islands)) {
        continue;
      }

      $this->profiles[(string) $id] = $profile;
      $this->islands[(string) $id] = $islands;
    }
  }

  /**
   * Builds an updater over every stored profile.
   *
   * @return self
   *   The updater.
   */
  public static function create(): self {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager'); // phpcs:ignore

    return new self($entity_type_manager);
  }

  /**
   * Lists the plugin IDs of every island of the given types.
   *
   * Use it to address a change at what an island *is* rather than at a list of
   * IDs, which would silently miss the islands other modules provide.
   *
   * @param \Drupal\display_builder\Island\IslandType ...$types
   *   The island types to match.
   *
   * @return string[]
   *   The matching island plugin IDs.
   */
  public static function islandIdsByType(IslandType ...$types): array {
    /** @var \Drupal\display_builder\Island\IslandPluginManagerInterface $island_manager */
    $island_manager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    \assert($island_manager instanceof IslandPluginManagerInterface);

    $ids = [];

    foreach ($island_manager->getDefinitions() as $plugin_id => $definition) {
      $type = $definition['type'] ?? NULL;

      if ($type instanceof IslandType && \in_array($type, $types, TRUE)) {
        $ids[] = (string) $plugin_id;
      }
    }

    return $ids;
  }

  /**
   * Renames an island, keeping its stored configuration.
   *
   * A profile already holding the new ID is left alone: it has been updated,
   * and its configuration is the one to keep.
   *
   * @param string $from
   *   The old island plugin ID.
   * @param string $to
   *   The new island plugin ID.
   *
   * @return self
   *   The updater, for chaining.
   */
  public function renameIsland(string $from, string $to): self {
    foreach ($this->islands as $profile_id => $islands) {
      if (!isset($islands[$from]) || isset($islands[$to])) {
        continue;
      }

      $islands[$to] = $islands[$from];
      unset($islands[$from]);
      $this->apply($profile_id, $islands);
    }

    return $this;
  }

  /**
   * Removes islands entirely, with all their configuration.
   *
   * @param string ...$island_ids
   *   The island plugin IDs to remove.
   *
   * @return self
   *   The updater, for chaining.
   */
  public function removeIsland(string ...$island_ids): self {
    foreach ($this->islands as $profile_id => $islands) {
      $found = \array_intersect($island_ids, \array_keys($islands));

      if (empty($found)) {
        continue;
      }

      foreach ($found as $island_id) {
        unset($islands[$island_id]);
      }

      $this->apply($profile_id, $islands);
    }

    return $this;
  }

  /**
   * Removes a configuration key from islands.
   *
   * @param string $key
   *   The island configuration key to remove.
   * @param string[] $island_ids
   *   (Optional) The islands to remove it from. Defaults to every island, for
   *   a key no island reads anymore.
   *
   * @return self
   *   The updater, for chaining.
   */
  public function removeKey(string $key, array $island_ids = []): self {
    return $this->each($island_ids, static function (array $configuration) use ($key): ?array {
      if (!\array_key_exists($key, $configuration)) {
        return NULL;
      }

      unset($configuration[$key]);

      return $configuration;
    });
  }

  /**
   * Renames a configuration key on islands, keeping its value.
   *
   * @param string $from
   *   The old key.
   * @param string $to
   *   The new key.
   * @param string[] $island_ids
   *   (Optional) The islands to rename it on. Defaults to every island.
   *
   * @return self
   *   The updater, for chaining.
   */
  public function renameKey(string $from, string $to, array $island_ids = []): self {
    return $this->each($island_ids, static function (array $configuration) use ($from, $to): ?array {
      if (!\array_key_exists($from, $configuration) || \array_key_exists($to, $configuration)) {
        return NULL;
      }

      $configuration[$to] = $configuration[$from];
      unset($configuration[$from]);

      return $configuration;
    });
  }

  /**
   * Sets a configuration key on islands, adding or replacing it.
   *
   * @param string $key
   *   The island configuration key.
   * @param mixed $value
   *   The value to store.
   * @param string[] $island_ids
   *   (Optional) The islands to set it on. Defaults to every island.
   *
   * @return self
   *   The updater, for chaining.
   */
  public function setKey(string $key, mixed $value, array $island_ids = []): self {
    return $this->each($island_ids, static function (array $configuration) use ($key, $value): ?array {
      if (\array_key_exists($key, $configuration) && $configuration[$key] === $value) {
        return NULL;
      }

      $configuration[$key] = $value;

      return $configuration;
    });
  }

  /**
   * Saves the profiles an operation changed.
   */
  public function save(): void {
    foreach (\array_keys($this->changed) as $profile_id) {
      $this->profiles[$profile_id]->set('islands', $this->islands[$profile_id]);
      $this->profiles[$profile_id]->save();
    }

    $this->changed = [];
  }

  /**
   * Runs a change over the configuration of the given islands.
   *
   * @param string[] $island_ids
   *   The islands to visit, or an empty array for every island.
   * @param callable $change
   *   Takes an island configuration array, returns the new one, or NULL when
   *   there is nothing to change - which is what keeps a profile out of the
   *   save list.
   *
   * @return self
   *   The updater, for chaining.
   */
  private function each(array $island_ids, callable $change): self {
    foreach ($this->islands as $profile_id => $islands) {
      $touched = FALSE;

      foreach ($islands as $island_id => $configuration) {
        if (!\is_array($configuration)) {
          continue;
        }

        if (!empty($island_ids) && !\in_array((string) $island_id, $island_ids, TRUE)) {
          continue;
        }

        $updated = $change($configuration);

        if ($updated === NULL) {
          continue;
        }

        $islands[$island_id] = $updated;
        $touched = TRUE;
      }

      if ($touched) {
        $this->apply($profile_id, $islands);
      }
    }

    return $this;
  }

  /**
   * Records the new islands map of a profile as changed.
   *
   * @param string $profile_id
   *   The profile ID.
   * @param array<string, mixed> $islands
   *   The updated islands map.
   */
  private function apply(string $profile_id, array $islands): void {
    $this->islands[$profile_id] = $islands;
    $this->changed[$profile_id] = TRUE;
  }

}
