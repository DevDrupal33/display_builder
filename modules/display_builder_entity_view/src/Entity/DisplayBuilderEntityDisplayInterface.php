<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\display_builder\Entity\ProfileInterface;

/**
 * Provides an interface for entity displays that have Display Builder.
 */
interface DisplayBuilderEntityDisplayInterface extends EntityViewDisplayInterface {

  /**
   * Determines if Display Builder is enabled.
   *
   * @return bool
   *   TRUE if Display Builder is enabled, FALSE otherwise.
   */
  public function isDisplayBuilderEnabled();

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
   * @return ?ProfileInterface
   *   The display builder config entity.
   */
  public function getDisplayBuilderOverrideProfile(): ?ProfileInterface;

}
