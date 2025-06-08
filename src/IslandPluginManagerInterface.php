<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Island plugin manager interface.
 */
interface IslandPluginManagerInterface extends PluginManagerInterface {

  /**
   * Get islands plugins instances.
   *
   * @param array $contexts
   *   (Optional) An array of context to pass to the display builder.
   *
   * @return array<string, IslandInterface>
   *   A list of instantiated plugins.
   */
  public function getIslands(array $contexts = []): array;

  /**
   * Get islands plugins by type.
   *
   * @param array $contexts
   *   (Optional) An array of context to pass to the display builder.
   * @param array $filter_by_island
   *   (Optional) Filter results by island ids.
   *
   * @return array
   *   A list of enabled islands sorted by weight.
   */
  public function getIslandsByTypes(array $contexts = [], array $filter_by_island = []): array;

  /**
   * Get the islands keyboard keys.
   *
   * @param array $filter_by_island
   *   (Optional) Filter list by island ids.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   A list of keys.
   */
  public function getIslandsKeyboard(array $filter_by_island = []): array;

}
