<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\ui_patterns\SourceInterface;

/**
 * Defines an interface for slot sources that support slots.
 */
interface SourceWithSlotsInterface extends SourceInterface {

  /**
   * Gets information about the slots.
   *
   * @return array<string, array{'title': string, 'description'?: string}>
   *   Information about the slots.
   */
  public function getSlotDefinitions(): array;

  /**
   * Gets slot path.
   *
   * To be used with Drupal\Component\Utility\NestedArray::setValue()
   *
   * @return array
   *   Each array item is a part of the path.
   */
  public static function getSlotPath(string $slot_id): array;

  /**
   * Get slot values.
   *
   * @return array
   *   Keys are slot IDs. Values are a list of slot sources.
   */
  public function getSlotValues(): array;

  /**
   * Set slot values.
   *
   * @param array $data
   *   Source data.
   * @param string $slot_id
   *   ID of the slot to update.
   * @param array $slot
   *   New slot data.
   *
   * @return array
   *   The updated source data.
   */
  public function setSlotValue(array $data, string $slot_id, array $slot): array;

  /**
   * Set slot renderable.
   *
   * @param array $build
   *   Source renderable.
   * @param string $slot_id
   *   ID of the slot to update.
   * @param array $slot
   *   New slot renderable.
   *
   * @return array
   *   The updated source renderable.
   */
  public function setSlotRenderable(array $build, string $slot_id, array $slot): array;

}
