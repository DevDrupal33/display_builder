<?php

declare(strict_types=1);

namespace Drupal\display_builder;

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
class SourceTree {

  /**
   * Source type: component.
   */
  private const SOURCE_TYPE_COMPONENT = 'component';

  /**
   * Source type: layout.
   */
  private const SOURCE_TYPE_LAYOUT = 'layout';

  /**
   * Source type: page_layout.
   */
  private const SOURCE_TYPE_PAGE_LAYOUT = 'page_layout';

  /**
   * Flat map of node data keyed by node_id.
   */
  protected array $nodes = [];

  /**
   * Hierarchical structure of node IDs.
   */
  protected array $structure = [];

  /**
   * List of root node IDs.
   */
  protected array $root = [];

  /**
   * Cached path index.
   */
  protected ?array $pathIndex = NULL;

  /**
   * Constructor.
   *
   * @param array $tree
   *   Initial nested tree data.
   */
  public function __construct(array $tree = []) {
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
    $this->pathIndex = NULL;

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
  protected function generateNodeId(): string {
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
  protected function normalize(array $items, ?string $parent_id, ?string $slot_id): array {
    $ids = [];

    foreach ($items as $item) {
      $node_id = $item['node_id'] ?? $this->generateNodeId();
      $ids[] = $node_id;

      $this->structure[$node_id] = [
        'parent' => $parent_id,
        'slot' => $slot_id,
        'slots' => $this->extractChildren($item, $node_id),
      ];

      $this->nodes[$node_id] = $this->cleanNodeData($item, $node_id);
    }

    return $ids;
  }

  /**
   * Extract children from a node into normalized slots.
   *
   * @param array $item
   *   The node item.
   * @param string $node_id
   *   The node ID.
   *
   * @return array
   *   Children IDs grouped by slot.
   */
  protected function extractChildren(array $item, string $node_id): array {
    $slots = [];
    $source_id = $item['source_id'] ?? '';
    $slots_data = $this->getSlotsBySourceType($item, $source_id);

    foreach ($slots_data as $slot_id => $data) {
      if ($source_id === self::SOURCE_TYPE_COMPONENT) {
        if (isset($data['sources'])) {
          $slots[$slot_id] = $this->normalize($data['sources'], $node_id, $slot_id);
        }
      }
      else {
        $slots[$slot_id] = $this->normalize($data, $node_id, $slot_id);
      }
    }

    return $slots;
  }

  /**
   * Clean node data by removing nested children.
   *
   * @param array $item
   *   The node item.
   * @param string $node_id
   *   The node ID.
   *
   * @return array
   *   The cleaned node data.
   */
  protected function cleanNodeData(array $item, string $node_id): array {
    $item['node_id'] = $node_id;
    $source_id = $item['source_id'] ?? '';

    if ($source_id === self::SOURCE_TYPE_COMPONENT && isset($item['source']['component']['slots'])) {
      foreach ($item['source']['component']['slots'] as $slot_id => $slot) {
        unset($item['source']['component']['slots'][$slot_id]['sources']);
      }
    }
    elseif (\in_array($source_id, [self::SOURCE_TYPE_LAYOUT, self::SOURCE_TYPE_PAGE_LAYOUT], TRUE) && isset($item['source']['regions'])) {
      foreach ($item['source']['regions'] as $slot_id => $region) {
        $item['source']['regions'][$slot_id] = [];
      }
    }

    return $item;
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
  protected function denormalize(array $ids): array {
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
  protected function injectChildren(array $node, array $slots): array {
    if (empty($slots)) {
      return $node;
    }

    $source_id = $node['source_id'] ?? '';

    if ($source_id === self::SOURCE_TYPE_COMPONENT) {
      foreach ($slots as $slot_id => $child_ids) {
        $node['source']['component']['slots'][$slot_id]['sources'] = $this->denormalize($child_ids);
      }
    }
    elseif (\in_array($source_id, [self::SOURCE_TYPE_LAYOUT, self::SOURCE_TYPE_PAGE_LAYOUT], TRUE)) {
      foreach ($slots as $slot_id => $child_ids) {
        $node['source']['regions'][$slot_id] = $this->denormalize($child_ids);
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
  protected function buildPathIndex(array $ids, array $current_path, array &$index): void {
    foreach ($ids as $idx => $id) {
      $path = \array_merge($current_path, [$idx]);
      $index[$id] = [
        'path' => $path,
        'parent' => $this->structure[$id]['parent'],
      ];

      $struct = $this->structure[$id];
      $source_id = $this->nodes[$id]['source_id'];

      foreach ($struct['slots'] as $slot_id => $child_ids) {
        if ($source_id === self::SOURCE_TYPE_COMPONENT) {
          $child_path = \array_merge($path, ['source', 'component', 'slots', $slot_id, 'sources']);
        }
        else {
          $child_path = \array_merge($path, ['source', 'regions', $slot_id]);
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
  protected function removeFromCurrentParent(string $node_id): bool {
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
  protected function recursiveRemove(string $node_id): void {
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
  protected function isDescendant(string $node_id, string $potential_ancestor_id): bool {
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
   * Helper to get slots from an item based on its source type.
   *
   * @param array $item
   *   The item.
   * @param string $source_id
   *   The source ID.
   *
   * @return array
   *   The slots.
   */
  private function getSlotsBySourceType(array $item, string $source_id): array {
    return match ($source_id) {
      self::SOURCE_TYPE_COMPONENT => $item['source']['component']['slots'] ?? [],
      self::SOURCE_TYPE_LAYOUT, self::SOURCE_TYPE_PAGE_LAYOUT => $item['source']['regions'] ?? [],
      default => [],
    };
  }

}
