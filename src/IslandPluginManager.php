<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\display_builder\Attribute\Island;

/**
 * Island plugin manager.
 */
final class IslandPluginManager extends DefaultPluginManager implements IslandPluginManagerInterface {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/display_builder/Island', $namespaces, $module_handler, IslandInterface::class, Island::class);
    $this->alterInfo('island_info');
    $this->setCacheBackend($cache_backend, 'island_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getIsland(string $island_id, array $contexts = []): IslandInterface {
    /** @var \Drupal\display_builder\IslandInterface $island */
    $island = $this->createInstance($island_id, ['contexts' => $contexts]);
    return $island;
  }

  /**
   * {@inheritdoc}
   */
  public function getIslands(array $contexts = []): array {
    return $this->createInstances($this->getDefinitions(), $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandsByTypes(array $contexts = [], ?array $filter_by_island = NULL): array {
    $result = [];

    foreach ($this->getIslands($contexts) as $island_id => $island) {
      if ($filter_by_island && !isset($filter_by_island[$island_id])) {
        continue;
      }
      /** @var \Drupal\display_builder\IslandType $type */
      $type = $island->getPluginDefinition()['type'];
      $result[$type->value][$island_id] = $island;
    }

    if ($filter_by_island) {
      $result = $this->sortListByWeight($result, $filter_by_island);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandsKeyboard(array $filter_by_island = []): array {
    $definitions = $this->getDefinitions();

    $grouped_definitions = [];

    foreach ($definitions as $definition) {
      if (isset($filter_by_island[$definition['id']])) {
        $grouped_definitions[$definition['type']->value][] = $definition;
      }
    }

    $all_shortcuts = [];

    foreach ($grouped_definitions as $definitions) {
      foreach ($definitions as $definition) {
        if (isset($definition['keyboard_shortcuts'])) {
          $all_shortcuts[] = $definition['keyboard_shortcuts'];
        }
      }
    }

    return \array_merge(...$all_shortcuts);
  }

  /**
   * Create a plugin instances for each definition.
   *
   * @param array $definitions
   *   An array of definitions.
   * @param array $contexts
   *   (Optional) An array of context to pass to the display builder.
   *
   * @return array
   *   A list of fully configured plugin instances.
   */
  protected function createInstances(array $definitions, array $contexts = []): array {
    return \array_map(
      function ($definition) use ($contexts) {
        return $this->createInstance($definition['id'], ['contexts' => $contexts]);
      },
      $definitions,
    );
  }

  /**
   * Sort by comparison a list.
   *
   * @param array $list
   *   The list to sort.
   * @param array<int,mixed> $weight
   *   The weigh mapping as id => weight.
   *
   * @return array<int,mixed>
   *   The sorted list.
   */
  private function sortListByWeight(array $list, array $weight): array {
    foreach ($list as &$items) {
      uksort($items, static function ($a, $b) use ($weight) {
        return $weight[$a] <=> $weight[$b];
      });
    }

    return $list;
  }

}
