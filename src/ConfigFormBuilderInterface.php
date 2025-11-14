<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Session\AccountInterface;

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
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   An entity or plugin allowing the use of Display Builder.
   * @param bool $mandatory
   *   (Optional). Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   *
   * @return array
   *   A form renderable array.
   */
  public function build(DisplayBuildableInterface $buildable, bool $mandatory = TRUE): array;

  /**
   * Get profiles allowed for the user.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Optional user account. Current user if empty.
   *
   * @return array
   *   The list of allowed profiles.
   */
  public function getAllowedProfiles(?AccountInterface $account = NULL): array;

  /**
   * Is the  user allowed to use display builder?
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   An entity allowing the use of Display Builder.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Optional user account. Current user if empty.
   *
   * @return bool
   *   Allowed or not.
   */
  public function isAllowed(DisplayBuildableInterface $buildable, ?AccountInterface $account = NULL): bool;

}
