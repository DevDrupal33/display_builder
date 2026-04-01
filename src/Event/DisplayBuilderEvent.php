<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\display_builder\InstanceInterface;

/**
 * Event fired when display builder is used.
 *
 * This is the base class for all display builder events. It carries only the
 * fields common to every event: the instance and the optional current island.
 * Per-event data (node ID, parent ID, payload array) lives in typed subclasses:
 *
 * - DisplayBuilderNodeEvent  — node_id (string, required)
 * - DisplayBuilderSlotEvent  — node_id + parent_id (both required strings)
 * - DisplayBuilderDeleteEvent — parent_id (?string, optional)
 * - DisplayBuilderDataEvent  — data (array, required)
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 * @see \Drupal\display_builder\Island\IslandEventSubscriberInterface
 */
class DisplayBuilderEvent extends Event {

  /**
   * The result for this event.
   *
   * A render array keyed by island ID.
   */
  private array $result = [];

  /**
   * Constructs a DisplayBuilderEvent object.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string|null $current_island_id
   *   Optional island ID that triggered the action. Islands matching this ID
   *   are skipped during fan-out to avoid redundant out-of-band swaps.
   */
  public function __construct(
    private InstanceInterface $instance,
    private ?string $current_island_id = NULL,
  ) {}

  /**
   * Append a result for this event.
   *
   * @param string $islandId
   *   The island ID.
   * @param array $result
   *   The result array to append.
   */
  public function appendResult(string $islandId, array $result): void {
    $this->result[$islandId] = $result;
  }

  /**
   * Gets the display builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface
   *   The display builder instance.
   */
  public function getInstance(): InstanceInterface {
    return $this->instance;
  }

  /**
   * Gets the enabled islands.
   *
   * @return array
   *   The enabled islands.
   */
  public function getIslandConfiguration(): array {
    return $this->instance->getProfile()->getIslandConfigurations();
  }

  /**
   * Gets the enabled islands.
   *
   * @return array
   *   The enabled islands.
   */
  public function getEnabledIslands(): array {
    return $this->instance->getProfile()->getEnabledIslands();
  }

  /**
   * Gets the current island ID.
   *
   * @return string|null
   *   The current island ID which trigger action.
   */
  public function getCurrentIslandId(): ?string {
    return $this->current_island_id;
  }

  /**
   * Gets the result for this event.
   *
   * @return array
   *   The result array, or empty array if not set.
   */
  public function getResult(): array {
    return $this->result;
  }

}
