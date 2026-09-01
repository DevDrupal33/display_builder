<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Provides an interface defining a display builder instance entity type.
 */
interface PublishableInterface {

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
   * Get the hash of the published data.
   *
   * @return ?int
   *   The hash of the published data. NULL if the display has never been
   *    published.
   */
  public function getPublishedHash(): ?int;

  /**
   * Get the time of the published data.
   *
   * @return ?int
   *   The timestamp of the published data. NULL if the display has never been
   *    published.
   */
  public function getPublishedTime(): ?int;

  /**
   * Publish current state.
   */
  public function publish(): void;

  /**
   * Restore to the last published state.
   *
   * No-op when nothing is published, so it never empties a display that
   * has no published state to restore to.
   */
  public function restore(): void;

  /**
   * Revert to the default/base configuration.
   *
   * Clears any overridden data and restores the default sources.
   */
  public function revert(): void;

}
