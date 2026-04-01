<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\InstanceInterface;

/**
 * Island events for builder save operations.
 *
 * Implement this interface to receive events when the builder state is
 * published to current config, or when a node is saved as a reusable preset.
 *
 * Covered events: ON_PUBLISH, ON_PRESET_SAVE.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 */
interface IslandSaveEventsInterface {

  /**
   * Event triggered when the builder state is saved to the current config.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onPublish(InstanceInterface $instance): array;

  /**
   * Event triggered when a node is saved as a reusable preset.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onPresetSave(InstanceInterface $instance): array;

}
