<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Interface for entities or plugins natively embedding a display builder.
 */
interface DisplayBuildableInterface {

  /**
   * Get the instance prefix.
   *
   * @return string
   *   The instance prefix.
   */
  public static function getPrefix(): string;

  /**
   * Get the context requirement.
   *
   * @return string
   *   The context requirement.
   */
  public static function getContextRequirement(): string;

  /**
   * Check if instance ID can be used with the interface implementation.
   *
   * @param string $instance_id
   *   Instance entity ID.
   *
   * @return array
   *   The parts we checked, extracted from the instance ID string.
   */
  public static function checkInstanceId(string $instance_id): ?array;

  /**
   * Get display builder instance URL.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public function getBuilderUrl(): Url;

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
   * Get display builder profile config entity.
   *
   * If NULL, the Display Builder is not activated for this entity.
   *
   * @return ?ProfileInterface
   *   The display builder profile config entity.
   */
  public function getProfile(): ?ProfileInterface;

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
   *   Instance entity ID.
   */
  public function getInstanceId(): ?string;

  /**
   * Checks access.
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
   * Init instance if missing.
   *
   * Init an display_builder_instance entity if:
   * - ::getProfile() is not null
   * - the instance is not already existing in storage.
   */
  public function initInstanceIfMissing(): void;

  /**
   * Initialize sources for this implementation.
   *
   * @return array
   *   The data.
   */
  public function getInitialSources(): array;

  /**
   * Initialize contexts for this implementation.
   *
   * @return array<\Drupal\Core\Plugin\Context\ContextInterface>
   *   The contexts.
   */
  public function getInitialContext(): array;

  /**
   * Get sources tree.
   *
   * @return array
   *   A list of nestable sources.
   */
  public function getSources(): array;

  /**
   * Save sources tree retrieved from the Instance entity to config or content.
   *
   * Triggered by a DisplayBuilderEvents::ON_SAVE event.
   */
  public function saveSources(): void;

}
