<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Island plugin manager interface.
 */
interface IslandPluginManagerInterface extends PluginManagerInterface {

  /**
   * Get islands plugins by type.
   *
   * @param array $contexts
   *   (Optional) An array of contexts.
   * @param array $configuration
   *   (Optional) An array of configuration.
   * @param array $filter_by_island
   *   (Optional) Filter results by island ids.
   *
   * @return array
   *   A list of enabled islands sorted by weight.
   */
  public function getIslandsByTypes(array $contexts = [], array $configuration = [], array $filter_by_island = []): array;

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

  /**
   * Create an island instance for each definition.
   *
   * @param array $definitions
   *   An array of plugin definitions.
   * @param array $contexts
   *   (Optional) An array of contexts.
   * @param array $configuration
   *   (Optional) An array of configuration.
   *
   * @return array<string, \Drupal\display_builder\IslandInterface>
   *   An array of island instances keyed by plugin ID.
   */
  public function createInstances(array $definitions, array $contexts = [], array $configuration = []): array;

}
