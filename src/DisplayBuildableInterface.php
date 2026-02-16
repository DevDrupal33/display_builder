<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Interface for entities or plugins natively embedding a display builder.
 */
interface DisplayBuildableInterface extends ContainerFactoryPluginInterface {

  // Storage property for of the override field.
  // This will we used in some schema.yml, careful if you change it.
  public const OVERRIDE_FIELD_PROPERTY = 'override_field';

  // Storage property for the overridden profile config entity ID.
  // This will we used in some schema.yml, careful if you change it.
  public const OVERRIDE_PROFILE_PROPERTY = 'override_profile';

  // Storage property for the profile config entity ID.
  // This will we used in some schema.yml, careful if you change it.
  public const PROFILE_PROPERTY = 'profile';

  // Storage property for the nestable list of UI Patterns 2 sources.
  // This will we used in some schema.yml, careful if you change it.
  public const SOURCES_PROPERTY = 'sources';

  /**
   * Build form for integration with Display Builder.
   *
   * @param bool $mandatory
   *   (Optional). Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   *
   * @return array
   *   A form renderable array.
   */
  public function buildInstanceForm(bool $mandatory = TRUE): array;

  /**
   * Checks access for an instance for a user account.
   *
   * @param string $instance_id
   *   Instance entity ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\display_builder\InstanceAccessControlHandler
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface;

  /**
   * Check if instance ID can be used with the interface implementation.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return array|null
   *   The parts we checked, extracted from the instance ID string.
   */
  public static function checkInstanceId(string $instance_id): ?array;

  /**
   * Collect instances related to this buildable.
   *
   * Null values are returned so the caller can decide to create the missing
   * Instance entities.
   *
   * @param \Drupal\display_builder\InstanceStorageInterface $instanceStorage
   *   The Display Builder instance storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   (Optional) The entity type manager service or null.
   *
   * @return array
   *   A associative array of Instance entities or null values.
   */
  public static function collectInstances(InstanceStorageInterface $instanceStorage, ?EntityTypeManagerInterface $entityTypeManager = NULL): array;

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
   * Get display builder instance URL.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public function getBuilderUrl(): Url;

  /**
   * Get the context requirement.
   *
   * @return string
   *   The context requirement.
   */
  public static function getContextRequirement(): string;

  /**
   * Get the display url that use this instance.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url;

  /**
   * Initialize contexts for this implementation.
   *
   * @return array<\Drupal\Core\Plugin\Context\ContextInterface>
   *   The contexts.
   */
  public function getInitialContext(): array;

  /**
   * Initialize sources for this implementation.
   *
   * @return array
   *   The data.
   */
  public function getInitialSources(): array;

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  public function getInstance(): ?InstanceInterface;

  /**
   * Get instance ID.
   *
   * Will be used as HTML id & class attributes and Javascript variables names
   * (because of HTMX) so must follow the intersection between:
   * - https://developer.mozilla.org/en-US/docs/Web/CSS/ident
   * - https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Lexical_grammar#identifiers
   * Characters can be any of the following:
   * - any ASCII character in the ranges A-Z and a-z
   * - any decimal digit (0 to 9), except for the first character
   * - an underscore (_)
   *
   * @return string|null
   *   The instance ID for the display builder, or NULL if the entity is new.
   */
  public function getInstanceId(): ?string;

  /**
   * Get the instance prefix.
   *
   * @return string
   *   The instance prefix.
   */
  public static function getPrefix(): string;

  /**
   * Get display builder profile config entity.
   *
   * If NULL, the Display Builder is not activated for this entity.
   *
   * @return ?ProfileInterface
   *   The display builder profile config entity.
   */
  public function getProfile(): ?ProfileInterface;

  /**
   * Get sources tree.
   *
   * @return array
   *   A list of nestable sources.
   */
  public function getSources(): array;

  /**
   * Get display builder instance URL from an instance ID.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public static function getUrlFromInstanceId(string $instance_id): Url;

  /**
   * Init instance if missing.
   *
   * Init an display_builder_instance entity if:
   * - ::getProfile() is not null
   * - the instance is not already existing in storage.
   */
  public function initInstanceIfMissing(): void;

  /**
   * Is the user allowed to use display builder.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Optional user account. Current user if empty.
   *
   * @return bool
   *   Allowed or not.
   */
  public function isAllowed(?AccountInterface $account = NULL): bool;

  /**
   * Save sources tree retrieved from the Instance entity to config or content.
   *
   * Triggered by a DisplayBuilderEvents::ON_SAVE event.
   */
  public function saveSources(): void;

}
