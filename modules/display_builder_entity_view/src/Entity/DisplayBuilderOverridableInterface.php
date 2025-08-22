<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\display_builder\DisplayBuilderInterface;

/**
 * Provides method to know if Display Builder is enabled.
 */
interface DisplayBuilderOverridableInterface {

  /**
   * Determines if the display allows custom overrides.
   *
   * @return bool
   *   TRUE if custom overrides are allowed, FALSE otherwise.
   */
  public function isDisplayBuilderOverridable(): bool;

  /**
   * Returns the field name used to store overridden displays.
   *
   * @return string|null
   *   The override field if available.
   */
  public function getDisplayBuilderOverrideField(): ?string;

  /**
   * Get display builder config entity for overridden view mode.
   *
   * @return ?DisplayBuilderInterface
   *   The display builder config entity.
   */
  public function getDisplayBuilderOverrideProfile(): ?DisplayBuilderInterface;

}
