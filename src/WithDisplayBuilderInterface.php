<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Url;

/**
 * Interface for entities natively embedding a display builder.
 *
 * So, it fits for `page_layout` entities, but not for `view` and
 * `entity_view_display` where Display builder is a non-native addition.
 */
interface WithDisplayBuilderInterface {

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
   *   Instance ID, as managed by the StateManager.
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
   *   Instance ID, as managed by the StateManager.
   *
   * @return \Drupal\Core\Url
   *   A Drupal URL object.
   */
  public static function getUrlFromInstanceId(string $instance_id): Url;

  /**
   * Get the display url that use this instance.
   *
   * @param string $instance_id
   *   Instance ID, as managed by the StateManager.
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
   * @return ?DisplayBuilderInterface
   *   The display builder profile config entity.
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface;

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
   *   Instance ID, as managed by the StateManager.
   */
  public function getInstanceId(): ?string;

  /**
   * Init instance if missing.
   *
   * Init an instance in the State Manager if:
   * - ::getDisplayBuilder() is not null
   * - the instance is not already present in the State Manager.
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
   * Save sources tree from the State Manager.
   *
   * When Display Builder needs to save the current state to the config on a
   * DisplayBuilderEvents::ON_SAVE event.
   */
  public function saveSources(): void;

}
