<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for buildable displays overriding other buildable displays.
 */
interface DisplayBuildableOverrideInterface extends DisplayBuildableInterface {

  // Storage property for of the override field.
  // This will we used in some schema.yml, careful if you change it.
  public const OVERRIDE_FIELD_PROPERTY = 'override_field';

  // Storage property for the overridden profile config entity ID.
  // This will we used in some schema.yml, careful if you change it.
  public const OVERRIDE_PROFILE_PROPERTY = 'override_profile';

  /**
   * Revert sources to the default/base configuration.
   *
   * Clears any overridden data and returns the base sources from the default
   * configuration. Called before the ON_REVERT event so islands receive the
   * final instance state.
   *
   * @return array
   *   The base sources from the default configuration.
   */
  public function revert(): array;

  /**
   * Get overridden buildable.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The overridden plugin instance.
   */
  public function getOverridden(): DisplayBuildableInterface;

}
