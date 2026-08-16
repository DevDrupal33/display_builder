<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\display_builder\Attribute\Island;

/**
 * Island plugin manager.
 */
final class IslandPluginManager extends DefaultPluginManager implements IslandPluginManagerInterface {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/display_builder/Island', $namespaces, $module_handler, IslandInterface::class, Island::class);
    $this->alterInfo('island_info');
    $this->setCacheBackend($cache_backend, 'island_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandsByTypes(array $contexts = [], array $configuration = [], ?array $filter_by_island = NULL): array {
    $result = [];

    foreach ($this->createInstances($this->getDefinitions(), $contexts, $configuration) as $island_id => $island) {
      if ($filter_by_island && !isset($filter_by_island[$island_id])) {
        continue;
      }
      /** @var \Drupal\display_builder\Island\IslandType $type */
      $type = $island->getPluginDefinition()['type'];
      $result[$type->value][$island_id] = $island;
    }

    if ($filter_by_island) {
      $result = $this->sortListByWeight($result, $filter_by_island);
    }

    return $result;
  }

  /**
   * Create a plugin instances for each definition.
   *
   * @param array $definitions
   *   An array of definitions.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   (Optional) An array of contexts, keyed by context name.
   * @param array $configuration
   *   (Optional) An array of configuration.
   *
   * @return array
   *   A list of fully configured plugin instances.
   */
  public function createInstances(array $definitions, array $contexts = [], array $configuration = []): array {
    return \array_map(
      function ($definition) use ($configuration, $contexts) {
        $config = $configuration[$definition['id']] ?? [];
        $config['contexts'] = $contexts;

        return $this->createInstance($definition['id'], $config);
      },
      $definitions,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $definition
   *   The plugin definition to process.
   * @param string $plugin_id
   *   The plugin ID.
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);

    if (!\is_array($definition)) {
      return;
    }

    // Resolve the region once, so every read is a plain definition lookup and
    // a plugin declaring a region its type does not have still lands in a
    // real one. @see \Drupal\display_builder\Island\IslandType::regions()
    $type = $definition['type'] instanceof IslandType ? $definition['type']->value : '';
    $region = $definition['region'] ?? NULL;

    if (!\is_string($region) || !\array_key_exists($region, IslandType::regions($type))) {
      $definition['region'] = IslandType::defaultRegion($type);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = $this->getDiscovery()->getDefinitions();

    foreach ($definitions as $plugin_id => &$definition) {
      $this->processDefinition($definition, $plugin_id);
    }
    $this->alterDefinitions($definitions);

    // If this plugin was provided by a module that does not exist, remove the
    // plugin definition.
    foreach ($definitions as $plugin_id => $plugin_definition) {
      $provider = $this->extractProviderFromDefinition($plugin_definition);

      if ($provider && !\in_array($provider, ['core', 'component'], TRUE) && !$this->providerExists($provider)) {
        unset($definitions[$plugin_id]);
      }

      if (!empty($plugin_definition['modules'] ?? [])) {
        foreach ($plugin_definition['modules'] as $module) {
          if (!$this->moduleHandler->moduleExists($module)) {
            unset($definitions[$plugin_id]);
          }
        }
      }
    }

    return $definitions;
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
      \uksort($items, static function ($a, $b) use ($weight) {
        return $weight[$a] <=> $weight[$b];
      });
    }

    return $list;
  }

}
