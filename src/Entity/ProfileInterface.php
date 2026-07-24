<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a display builder entity type.
 */
interface ProfileInterface extends ConfigEntityInterface {

  /**
   * Get enabled islands for this config.
   *
   * @return array
   *   The islands as island ID => weight.
   */
  public function getEnabledIslands(): array;

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
   * Whether the library panels are merged into a single flat list.
   *
   * @return bool
   *   TRUE if library panels (Components, Blocks, Presets...) are merged
   *   into a single flat list without tabs, FALSE otherwise.
   */
  public function isLibraryFlat(): bool;

  /**
   * How library tabs (Components, Blocks, Presets...) should be displayed.
   *
   * @return string
   *   One of 'label', 'icon' or 'icon_label'.
   */
  public function getLibraryTabsDisplay(): string;

  /**
   * How contextual panel tabs should be displayed.
   *
   * @return string
   *   One of 'label', 'icon' or 'icon_label'.
   */
  public function getContextualTabsDisplay(): string;

  /**
   * How View panels (main area tabs, sidebar buttons) should be displayed.
   *
   * @return string
   *   One of 'label', 'icon' or 'icon_label'.
   */
  public function getViewPanelsDisplay(): string;

}
