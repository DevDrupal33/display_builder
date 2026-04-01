<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\ui_patterns\SourceInterface;

/**
 * Manages hierarchical data with a high-performance normalized structure.
 *
 * This class converts nested source trees into a flat internal representation:
 * - A 'nodes' map containing the raw configuration for each unique node.
 * - A 'structure' map defining parent-child relationships and slot assignments.
 *
 * This normalization allows for O(1) or O(log N) operations when moving,
 * updating, or retrieving specific nodes, regardless of tree depth. The
 * tree is only denormalized back into a nested format when requested via
 * ::getTree() for rendering or persistence.
 */
final class SourceTree {

  /**
   * Flat map of node data keyed by node_id.
   */
  private array $nodes = [];

  /**
   * Hierarchical structure of node IDs.
   */
  private array $structure = [];

  /**
   * List of root node IDs.
   */
  private array $root = [];

  /**
   * Cached path index.
   */
  private ?array $pathIndex = NULL;

  /**
   * The source plugin manager.
   */
  private ?PluginManagerInterface $sourceManager = NULL;

  /**
   * Cache of resolved plugin classes keyed by source_id.
   */
  private array $pluginClassCache = [];

  /**
   * Constructor.
   *
   * @param array $tree
   *   Initial nested tree data.
   * @param \Drupal\Component\Plugin\PluginManagerInterface|null $sourceManager
   *   The source plugin manager.
   */
  public function __construct(array $tree = [], ?PluginManagerInterface $sourceManager = NULL) {
    $this->sourceManager = $sourceManager;
    $this->rebuild($tree);
  }

  /**
   * Rebuild the internal normalized state from a nested tree.
   *
   * @param array $tree
   *   The nested tree data.
   */
  public function rebuild(array $tree): void {
    $this->nodes = [];
    $this->structure = [];
    $this->pluginClassCache = [];
    $this->root = $this->normalize($tree, NULL, NULL);
    $this->pathIndex = NULL;
  }

  /**
   * Get the nested tree data.
   *
   * @return array
   *   The full nested tree data.
   */
  public function getTree(): array {
    return $this->denormalize($this->root);
  }

  /**
   * Get the raw normalized structure (nodes, structure, root).
   *
   * Returns the internal flat representation before denormalization, useful
   * for debugging and dev tooling.
   *
   * @return array
   *   An array with keys 'nodes', 'structure', and 'root'.
   */
  public function getNormalizedStructure(): array {
    return [
      'nodes' => $this->nodes,
      'structure' => $this->structure,
      'root' => $this->root,
    ];
  }

  /**
   * Get the path index.
   *
   * @return array
   *   The path index.
   */
  public function getPathIndex(): array {
    if ($this->pathIndex === NULL) {
      $this->pathIndex = [];
      $this->buildPathIndex($this->root, [], $this->pathIndex);
    }

    return $this->pathIndex;
  }

  /**
   * Attach a new source to root.
   *
   * @param int $position
   *   The position in the root list.
   * @param string $source_id
   *   The source plugin ID.
   * @param array $source_data
   *   The source configuration data.
   *
   * @return string
   *   The new node ID.
   */
  public function attachToRoot(int $position, string $source_id, array $source_data): string {
    $node_id = $this->generateNodeId();
    $this->nodes[$node_id] = [
      'source_id' => $source_id,
      'source' => $source_data,
    ];
    $this->structure[$node_id] = [
      'parent' => NULL,
      'slot' => NULL,
      'slots' => [],
    ];
    \array_splice($this->root, $position, 0, [$node_id]);
    $this->pathIndex = NULL;

    return $node_id;
  }

  /**
   * Attach a new source to a slot.
   *
   * @param string $parent_id
   *   The parent node ID.
   * @param string $slot_id
   *   The slot ID.
   * @param int $position
   *   The position in the slot.
   * @param string $source_id
   *   The source plugin ID.
   * @param array $source_data
   *   The source configuration data.
   *
   * @return string|null
   *   The new node ID or NULL if parent not found.
   */
  public function attachToSlot(string $parent_id, string $slot_id, int $position, string $source_id, array $source_data): ?string {
    if (!isset($this->structure[$parent_id])) {
      return NULL;
    }

    $node_id = $this->generateNodeId();
    $this->nodes[$node_id] = [
      'source_id' => $source_id,
      'source' => $source_data,
    ];
    $this->structure[$node_id] = [
      'parent' => $parent_id,
      'slot' => $slot_id,
      'slots' => [],
    ];

    if (!isset($this->structure[$parent_id]['slots'][$slot_id])) {
      $this->structure[$parent_id]['slots'][$slot_id] = [];
    }
    \array_splice($this->structure[$parent_id]['slots'][$slot_id], $position, 0, [$node_id]);
    $this->pathIndex = NULL;

    return $node_id;
  }

  /**
   * Move node to root.
   *
   * @param string $node_id
   *   The node ID to move.
   * @param int $position
   *   The new position in the root list.
   *
   * @return bool
   *   TRUE if success.
   */
  public function moveToRoot(string $node_id, int $position): bool {
    if (!$this->removeFromCurrentParent($node_id)) {
      return FALSE;
    }

    $this->structure[$node_id]['parent'] = NULL;
    $this->structure[$node_id]['slot'] = NULL;
    \array_splice($this->root, $position, 0, [$node_id]);
    $this->pathIndex = NULL;

    return TRUE;
  }

  /**
   * Move node to a slot.
   *
   * @param string $node_id
   *   The node ID to move.
   * @param string $parent_id
   *   The target parent ID.
   * @param string $slot_id
   *   The target slot ID.
   * @param int $position
   *   The position in the target slot.
   *
   * @return bool
   *   TRUE if success.
   */
  public function moveToSlot(string $node_id, string $parent_id, string $slot_id, int $position): bool {
    if (!isset($this->structure[$parent_id])) {
      return FALSE;
    }

    // Forbidden move: moving a parent into its own descendant.
    if ($this->isDescendant($parent_id, $node_id)) {
      return FALSE;
    }

    if (!$this->removeFromCurrentParent($node_id)) {
      return FALSE;
    }

    $this->structure[$node_id]['parent'] = $parent_id;
    $this->structure[$node_id]['slot'] = $slot_id;

    if (!isset($this->structure[$parent_id]['slots'][$slot_id])) {
      $this->structure[$parent_id]['slots'][$slot_id] = [];
    }
    \array_splice($this->structure[$parent_id]['slots'][$slot_id], $position, 0, [$node_id]);
    $this->pathIndex = NULL;

    return TRUE;
  }

  /**
   * Remove a node and its descendants.
   *
   * @param string $node_id
   *   The node ID to remove.
   *
   * @return bool
   *   TRUE if success.
   */
  public function remove(string $node_id): bool {
    if (!$this->removeFromCurrentParent($node_id)) {
      return FALSE;
    }
    $this->recursiveRemove($node_id);
    $this->pathIndex = NULL;

    return TRUE;
  }

  /**
   * Check if a node exists.
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return bool
   *   TRUE if it exists.
   */
  public function hasNode(string $node_id): bool {
    return isset($this->nodes[$node_id]);
  }

  /**
   * Get flat node data (no children).
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return array|null
   *   The flat node data or NULL.
   */
  public function getNodeData(string $node_id): ?array {
    return $this->nodes[$node_id] ?? NULL;
  }

  /**
   * Get a node data by ID (nested subtree).
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return array|null
   *   The node data (nested structure for that node) or NULL.
   */
  public function getNode(string $node_id): ?array {
    if (!isset($this->nodes[$node_id])) {
      return NULL;
    }

    return $this->denormalize([$node_id])[0];
  }

  /**
   * Get the parent ID of a node.
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return string|null
   *   The parent ID or NULL if at root.
   */
  public function getParentId(string $node_id): ?string {
    return $this->structure[$node_id]['parent'] ?? NULL;
  }

  /**
   * Set source data for a node.
   *
   * @param string $node_id
   *   The node ID.
   * @param string $source_id
   *   The source plugin ID.
   * @param array $source_data
   *   The source configuration data.
   *
   * @return bool
   *   TRUE if success.
   */
  public function setSource(string $node_id, string $source_id, array $source_data): bool {
    if (!isset($this->nodes[$node_id])) {
      return FALSE;
    }
    $this->nodes[$node_id]['source_id'] = $source_id;
    $this->nodes[$node_id]['source'] = $source_data;

    return TRUE;
  }

  /**
   * Set third party settings for a node.
   *
   * @param string $node_id
   *   The node ID.
   * @param string $island_id
   *   The island (plugin) ID.
   * @param array $data
   *   The third party settings data.
   *
   * @return bool
   *   TRUE if success.
   */
  public function setThirdPartySettings(string $node_id, string $island_id, array $data): bool {
    if (!isset($this->nodes[$node_id])) {
      return FALSE;
    }

    if (!isset($this->nodes[$node_id]['third_party_settings'])) {
      $this->nodes[$node_id]['third_party_settings'] = [];
    }
    $this->nodes[$node_id]['third_party_settings'][$island_id] = $data;

    return TRUE;
  }

  /**
   * Generate a unique node ID.
   *
   * @return string
   *   The generated node ID.
   */
  private function generateNodeId(): string {
    return \bin2hex(\random_bytes(8));
  }

  /**
   * Normalize a nested tree into flat maps.
   *
   * @param array $items
   *   Nested items.
   * @param string|null $parent_id
   *   Current parent ID.
   * @param string|null $slot_id
   *   Current slot ID.
   *
   * @return array
   *   List of node IDs at this level.
   */
  private function normalize(array $items, ?string $parent_id, ?string $slot_id): array {
    $ids = [];

    foreach ($items as $item) {
      $node_id = $item['node_id'] ?? $this->generateNodeId();
      $ids[] = $node_id;

      $source_id = $item['source_id'] ?? '';
      $plugin = $this->getSourcePlugin($source_id, $item['source'] ?? []);

      $slots = [];

      if ($plugin instanceof SourceWithSlotsInterface) {
        foreach ($plugin->getSlotValues() as $child_slot_id => $data) {
          $slots[$child_slot_id] = $this->normalize($data, $node_id, $child_slot_id);
        }

        foreach ($plugin->getSlotDefinitions() as $child_slot_id => $_) {
          $path = $plugin::getSlotPath($child_slot_id);
          NestedArray::unsetValue($item['source'], $path);
        }
      }

      $item['node_id'] = $node_id;
      $this->structure[$node_id] = [
        'parent' => $parent_id,
        'slot' => $slot_id,
        'slots' => $slots,
      ];
      $this->nodes[$node_id] = $item;
    }

    return $ids;
  }

  /**
   * Denormalize a list of node IDs into a nested tree.
   *
   * @param array $ids
   *   The IDs to assemble.
   *
   * @return array
   *   The nested tree.
   */
  private function denormalize(array $ids): array {
    return \array_map(function ($id) {
      $node = $this->nodes[$id];
      $node['node_id'] = $id;

      return $this->injectChildren($node, $this->structure[$id]['slots']);
    }, $ids);
  }

  /**
   * Inject children IDs back into a node as nested data.
   *
   * @param array $node
   *   The node data.
   * @param array $slots
   *   The slots with children IDs.
   *
   * @return array
   *   The node data with children injected.
   */
  private function injectChildren(array $node, array $slots): array {
    if (empty($slots)) {
      return $node;
    }

    $source_id = $node['source_id'] ?? '';
    $class = $this->getPluginClass($source_id);

    if ($class && \is_subclass_of($class, SourceWithSlotsInterface::class)) {
      foreach ($slots as $slot_id => $child_ids) {
        $path = $class::getSlotPath($slot_id);
        NestedArray::setValue($node['source'], $path, $this->denormalize($child_ids));
      }
    }

    return $node;
  }

  /**
   * Build the path index recursively.
   *
   * @param array $ids
   *   Current IDs level.
   * @param array $current_path
   *   Current path keys.
   * @param array $index
   *   The index to populate.
   */
  private function buildPathIndex(array $ids, array $current_path, array &$index): void {
    foreach ($ids as $idx => $id) {
      $path = [...$current_path, $idx];
      $index[$id] = [
        'path' => $path,
        'parent' => $this->structure[$id]['parent'],
      ];

      $struct = $this->structure[$id];
      $source_id = $this->nodes[$id]['source_id'];
      $class = $this->getPluginClass($source_id);

      foreach ($struct['slots'] as $slot_id => $child_ids) {
        if ($class && \is_subclass_of($class, SourceWithSlotsInterface::class)) {
          $child_path = [...$path, 'source', ...$class::getSlotPath($slot_id)];
        }
        else {
          continue;
        }
        $this->buildPathIndex($child_ids, $child_path, $index);
      }
    }
  }

  /**
   * Remove a node from its parent's children list.
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return bool
   *   TRUE if found and removed.
   */
  private function removeFromCurrentParent(string $node_id): bool {
    if (!isset($this->structure[$node_id])) {
      return FALSE;
    }

    $parent_id = $this->structure[$node_id]['parent'];
    $slot_id = $this->structure[$node_id]['slot'];

    if ($parent_id === NULL) {
      $key = \array_search($node_id, $this->root, TRUE);

      if ($key === FALSE) {
        return FALSE;
      }
      \array_splice($this->root, (int) $key, 1);

      return TRUE;
    }

    if ($slot_id === NULL || !isset($this->structure[$parent_id]['slots'][$slot_id])) {
      return FALSE;
    }

    $child_ids = &$this->structure[$parent_id]['slots'][$slot_id];

    if (!\is_array($child_ids)) {
      return FALSE;
    }
    $key = \array_search($node_id, $child_ids, TRUE);

    if ($key !== FALSE) {
      \array_splice($child_ids, (int) $key, 1);

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Recursively remove node and data.
   *
   * @param string $node_id
   *   The node ID.
   */
  private function recursiveRemove(string $node_id): void {
    if (!isset($this->structure[$node_id])) {
      return;
    }

    foreach ($this->structure[$node_id]['slots'] as $child_ids) {
      foreach ($child_ids as $child_id) {
        $this->recursiveRemove($child_id);
      }
    }
    unset($this->nodes[$node_id], $this->structure[$node_id]);
  }

  /**
   * Check if a node is a descendant of another.
   *
   * @param string $node_id
   *   The node ID to check.
   * @param string $potential_ancestor_id
   *   The potential ancestor ID.
   *
   * @return bool
   *   TRUE if descendant.
   */
  private function isDescendant(string $node_id, string $potential_ancestor_id): bool {
    $current_parent = $this->getParentId($node_id);

    while ($current_parent !== NULL) {
      if ($current_parent === $potential_ancestor_id) {
        return TRUE;
      }
      $current_parent = $this->getParentId($current_parent);
    }

    return FALSE;
  }

  /**
   * Get source plugin instance.
   *
   * @param string $source_id
   *   The source plugin ID.
   * @param array $source_configuration
   *   The source configuration.
   *
   * @return \Drupal\ui_patterns\SourceInterface|null
   *   The source plugin instance or NULL.
   */
  private function getSourcePlugin(string $source_id, array $source_configuration): ?SourceInterface {
    try {
      $plugin = $this->getSourceManager()->createInstance($source_id, ['settings' => $source_configuration]);

      return $plugin instanceof SourceInterface ? $plugin : NULL;
    }
    catch (\Exception $e) {
      // phpcs:ignore -- lazy-init required; see getSourceManager() docblock.
      \Drupal::logger('display_builder')->warning('SourceTree: failed to instantiate source plugin %id: @message', ['%id' => $source_id, '@message' => $e->getMessage()]);

      return NULL;
    }
  }

  /**
   * Get source plugin class.
   *
   * @param string $source_id
   *   The source plugin ID.
   *
   * @return string|null
   *   The plugin class or NULL.
   */
  private function getPluginClass(string $source_id): ?string {
    if (\array_key_exists($source_id, $this->pluginClassCache)) {
      return $this->pluginClassCache[$source_id];
    }

    try {
      $definition = $this->getSourceManager()->getDefinition($source_id);
      $this->pluginClassCache[$source_id] = $definition['class'] ?? NULL;
    }
    catch (\Exception $e) {
      // phpcs:ignore -- lazy-init required; see getSourceManager() docblock.
      \Drupal::logger('display_builder')->warning('SourceTree: failed to get definition for source plugin %id: @message', ['%id' => $source_id, '@message' => $e->getMessage()]);
      $this->pluginClassCache[$source_id] = NULL;
    }

    return $this->pluginClassCache[$source_id];
  }

  /**
   * Gets the UI Patterns source plugin manager.
   *
   * SourceTree is a plain value object instantiated as new SourceTree() from
   * entity and plugin base classes where constructor injection is unavailable.
   * The lazy-init fallback using \Drupal::service() is intentional and the
   * only viable pattern for those call sites.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The source plugin manager.
   */
  private function getSourceManager(): PluginManagerInterface {
    if ($this->sourceManager === NULL) {
      // phpcs:ignore -- lazy-init required; see method docblock.
      $this->sourceManager = \Drupal::service('plugin.manager.ui_patterns_source');
    }

    return $this->sourceManager;
  }

}
