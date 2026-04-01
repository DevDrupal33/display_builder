<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Provides an interface defining a display builder instance entity type.
 */
interface PublishableInterface {

  /**
   * Check display has required context, meaning it can save value.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function isPublishable(): bool;

  /**
   * Check display has required context, meaning it can save value.
   *
   * @param string $key
   *   The context key to look for.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   (Optional) contexts if already accessible, keyed by context name.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function hasSaveContextsRequirement(string $key, array $contexts = []): bool;

  /**
   * If display builder has been saved.
   *
   * @return bool
   *   Has save data.
   */
  public function isPublished(): bool;

  /**
   * The save value is the current value of display builder.
   *
   * @return bool
   *   The save is the current or not.
   */
  public function isPublishedPresent(): bool;

  /**
   * Restore to the last saved state.
   */
  public function restore(): void;

}
