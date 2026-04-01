<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\display_builder\InstanceInterface;

/**
 * Event carrying a required data payload array.
 *
 * Dispatched for ON_ACTIVE (node data) and ON_PUBLISH (context data).
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 */
final class DisplayBuilderDataEvent extends DisplayBuilderEvent {

  /**
   * Constructs a DisplayBuilderDataEvent.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param array $data
   *   The data payload (node data for ON_ACTIVE, contexts for ON_PUBLISH).
   * @param string|null $current_island_id
   *   The island ID that triggered the action, if any.
   */
  public function __construct(
    InstanceInterface $instance,
    private array $data,
    ?string $current_island_id = NULL,
  ) {
    parent::__construct($instance, $current_island_id);
  }

  /**
   * Gets the data payload.
   *
   * @return array
   *   The data array (guaranteed non-null for this event type).
   */
  public function getData(): array {
    return $this->data;
  }

}
