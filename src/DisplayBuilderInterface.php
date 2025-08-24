<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a display builder entity type.
 */
interface DisplayBuilderInterface extends ConfigEntityInterface {

  /**
   * Get enabled islands for this config.
   *
   * @return array
   *   The islands as island ID => weight.
   */
  public function getIslandEnabled(): array;

  /**
   * Get configuration of all islands.
   *
   * @return array
   *   Configuration keyed by island ID.
   */
  public function getIslandConfigurations(): array;

  /**
   * Gets configuration for an island.
   *
   * @param string $island_id
   *   The island ID.
   *
   * @return array
   *   The island configuration.
   */
  public function getIslandConfiguration(string $island_id): array;

  /**
   * Set configuration for an island.
   *
   * @param string $island_id
   *   The island ID.
   * @param array $configuration
   *   The island configuration.
   */
  public function setIslandConfiguration(string $island_id, array $configuration = []): void;

  /**
   * Returns the machine-readable permission name for the display builder.
   *
   * @return string
   *   The machine-readable permission name.
   */
  public function getPermissionName(): string;

  /**
   * Get roles allowed to use the Display builder.
   *
   * @return array
   *   List of roles.
   */
  public function getRoles(): array;

  /**
   * Return the library 'mode'.
   *
   * @todo To remove once we find a better way of switching between CDN and
   * local.
   *
   * @return string
   *   'cdn' or 'local'
   */
  public function getLibrary(): string;

  /**
   * Is debug mode activated?
   *
   * @return bool
   *   Activated or not.
   */
  public function isDebugModeActivated(): bool;

}
