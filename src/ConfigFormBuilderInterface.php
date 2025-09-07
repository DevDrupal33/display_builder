<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for the config form builder.
 */
interface ConfigFormBuilderInterface {

  // Storage property for the profile config entity ID.
  // This will we used in some schema.yml, careful if you change it.
  public const PROFILE_PROPERTY = 'profile';

  // Storage property for the nestable list of UI Patterns 2 sources.
  // This will we used in some schema.yml, careful if you change it.
  public const SOURCES_PROPERTY = 'sources';

  // Storage property for the overridden profile config entity ID.
  // This will we used in some schema.yml, careful if you change it.
  public const OVERRIDE_PROFILE_PROPERTY = 'override_profile';

  // Storage property for of the override field.
  // This will we used in some schema.yml, careful if you change it.
  public const OVERRIDE_FIELD_PROPERTY = 'override_field';

  /**
   * Build form for integration with Display Builder.
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $entity
   *   An entity allowing the use of Display Builder.
   * @param bool $mandatory
   *   (Optional). Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   *
   * @return array
   *   A form renderable array.
   */
  public function build(DisplayBuildableInterface $entity, bool $mandatory = TRUE): array;

  /**
   * Get profiles allowed for the current user.
   *
   * @return array
   *   The list of allowed profiles.
   */
  public function getAllowedProfiles(): array;

}
