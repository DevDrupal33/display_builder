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
   * Returns the machine-readable permission name for the text format.
   *
   * @return string
   *   The machine-readable permission name.
   */
  public function getPermissionName(): string;

}
