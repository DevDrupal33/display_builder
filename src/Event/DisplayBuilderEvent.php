<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired when display builder is used.
 */
final class DisplayBuilderEvent extends Event {

  /**
   * The result for this event.
   *
   * A render array.
   */
  private array $result = [];

  /**
   * Constructs a DisplayBuilderEvent object.
   *
   * @param string $builder_id
   *   The display builder ID.
   * @param array $island_enabled
   *   The enabled islands.
   * @param array $island_configuration
   *   The island configuration.
   * @param array|null $data
   *   The data associated with this event.
   * @param string|null $node_id
   *   The tree node ID.
   * @param string|null $parent_id
   *   The parent node ID.
   * @param string|null $current_island_id
   *   Optional current island ID which trigger action.
   */
  public function __construct(
    private string $builder_id,
    private array $island_enabled,
    private array $island_configuration,
    private ?array $data = NULL,
    private ?string $node_id = NULL,
    private ?string $parent_id = NULL,
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
   * Gets the display builder ID.
   *
   * @return string
   *   The display builder ID.
   */
  public function getBuilderId(): string {
    return $this->builder_id;
  }

  /**
   * Gets the data associated with this event.
   *
   * @return array|null
   *   The event data.
   */
  public function getData(): ?array {
    return $this->data;
  }

  /**
   * Gets the tree node ID.
   *
   * @return string
   *   The tree node ID.
   */
  public function getNodeId(): ?string {
    return $this->node_id;
  }

  /**
   * Gets the enabled islands.
   *
   * @return array
   *   The enabled islands.
   */
  public function getIslandConfiguration(): array {
    return $this->island_configuration;
  }

  /**
   * Gets the enabled islands.
   *
   * @return array
   *   The enabled islands.
   */
  public function getEnabledIslands(): array {
    return $this->island_enabled;
  }

  /**
   * Gets the parent node ID.
   *
   * @return string
   *   The parent node ID.
   */
  public function getParentId(): ?string {
    return $this->parent_id;
  }

  /**
   * Gets the current island ID.
   *
   * @return string
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
