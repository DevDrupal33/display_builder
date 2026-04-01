<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\InstanceInterface;

/**
 * Island event for node focus / active selection.
 *
 * Implement this interface to receive the event when a node becomes the
 * active / focused node in the UI.
 *
 * Covered event: ON_ACTIVE.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 */
interface IslandActiveEventInterface {

  /**
   * Event triggered when a node becomes the active / focused node in the UI.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   * @param array $data
   *   The node data of the newly active node.
   *
   * @return array
   *   A render array with out-of-band HTMX commands, or empty array.
   */
  public function onActive(InstanceInterface $instance, array $data): array;

}
