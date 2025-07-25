<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a display builder entity type.
 */
interface DisplayBuilderInterface extends ConfigEntityInterface {

  /**
   * Builds and returns the renderable array for this display builder.
   *
   * @param string $builder_id
   *   The ID of the display builder instance.
   * @param array $contexts
   *   (Optional) An array of context to pass to the display builder.
   *
   * @return array
   *   A renderable array representing the content of the display builder.
   *
   * @see \Drupal\display_builder\DisplayBuilderViewBuilder
   */
  public function build(string $builder_id, array $contexts = []): array;

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

}
