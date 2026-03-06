<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\display_builder\SourceTree;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SourceTree class.
 *
 * @internal
 */
#[CoversClass(SourceTree::class)]
#[Group('display_builder')]
final class SourceTreeTest extends UnitTestCase {

  /**
   * Test basic initialization and indexing.
   */
  public function testInitialization(): void {
    $initial_tree = [
      [
        'source_id' => 'textfield',
        'source' => ['value' => 'A'],
      ],
      [
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'comp_1',
            'slots' => [
              'slot_1' => [
                'sources' => [
                  [
                    'source_id' => 'textfield',
                    'source' => ['value' => 'B'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $data_tree = new SourceTree($initial_tree);
    $tree = $data_tree->getTree();

    self::assertCount(2, $tree);
    self::assertArrayHasKey('node_id', $tree[0]);
    self::assertArrayHasKey('node_id', $tree[1]);
    self::assertArrayHasKey('node_id', $tree[1]['source']['component']['slots']['slot_1']['sources'][0]);
    self::assertSame('A', $tree[0]['source']['value']);
    self::assertSame('B', $tree[1]['source']['component']['slots']['slot_1']['sources'][0]['source']['value']);
  }

  /**
   * Test with layout sources.
   */
  public function testLayoutSources(): void {
    $initial_tree = [
      [
        'source_id' => 'layout',
        'source' => [
          'plugin_id' => 'onecol',
          'regions' => [
            'content' => [
              [
                'source_id' => 'textfield',
                'source' => ['value' => 'Inside Layout'],
              ],
            ],
          ],
        ],
      ],
    ];

    $data_tree = new SourceTree($initial_tree);
    $tree = $data_tree->getTree();

    self::assertCount(1, $tree);
    self::assertSame('layout', $tree[0]['source_id']);
    self::assertCount(1, $tree[0]['source']['regions']['content']);
    self::assertSame('Inside Layout', $tree[0]['source']['regions']['content'][0]['source']['value']);
  }

  /**
   * Test with page_layout sources.
   */
  public function testPageLayoutSources(): void {
    $initial_tree = [
      [
        'source_id' => 'page_layout',
        'source' => [
          'regions' => [
            'header' => [
              [
                'source_id' => 'textfield',
                'source' => ['value' => 'Header Content'],
              ],
            ],
          ],
        ],
      ],
    ];

    $data_tree = new SourceTree($initial_tree);
    $tree = $data_tree->getTree();

    self::assertCount(1, $tree);
    self::assertSame('page_layout', $tree[0]['source_id']);
    self::assertArrayHasKey('node_id', $tree[0]);
    self::assertNotEmpty($tree[0]['source']['regions']['header']);
    self::assertArrayHasKey('node_id', $tree[0]['source']['regions']['header'][0]);
    self::assertSame('Header Content', $tree[0]['source']['regions']['header'][0]['source']['value']);
  }

  /**
   * Test attachToRoot.
   */
  public function testAttachToRoot(): void {
    $data_tree = new SourceTree();
    $id1 = $data_tree->attachToRoot(0, 'textfield', ['value' => '1']);
    $id2 = $data_tree->attachToRoot(0, 'textfield', ['value' => '2']);

    $tree = $data_tree->getTree();
    self::assertCount(2, $tree);
    self::assertSame($id2, $tree[0]['node_id']);
    self::assertSame($id1, $tree[1]['node_id']);
  }

  /**
   * Test attachToSlot.
   */
  public function testAttachToSlot(): void {
    $data_tree = new SourceTree();
    $parent_id = $data_tree->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'comp_1'],
    ]);

    $child_id = $data_tree->attachToSlot($parent_id, 'slot_1', 0, 'textfield', ['value' => 'child']);
    self::assertNotNull($child_id);

    $tree = $data_tree->getTree();
    self::assertSame($child_id, $tree[0]['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
  }

  /**
   * Test moveToRoot.
   */
  public function testMoveToRoot(): void {
    $data_tree = new SourceTree();
    $parent_id = $data_tree->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'comp_1'],
    ]);
    $child_id = $data_tree->attachToSlot($parent_id, 'slot_1', 0, 'textfield', ['value' => 'child']);

    self::assertTrue($data_tree->moveToRoot($child_id, 1));

    $tree = $data_tree->getTree();
    self::assertCount(2, $tree);
    self::assertSame($parent_id, $tree[0]['node_id']);
    self::assertSame($child_id, $tree[1]['node_id']);
    self::assertEmpty($tree[0]['source']['component']['slots']['slot_1']['sources']);
  }

  /**
   * Test moveToSlot.
   */
  public function testMoveToSlot(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'comp_A'],
    ]);
    $node_b = $data_tree->attachToRoot(1, 'component', [
      'component' => ['component_id' => 'comp_B'],
    ]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);

    // Move C from B to A.
    self::assertTrue($data_tree->moveToSlot($node_c, $node_a, 'slot_1', 0));

    $tree = $data_tree->getTree();
    self::assertSame($node_c, $tree[0]['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
    self::assertEmpty($tree[1]['source']['component']['slots']['slot_1']['sources']);
  }

  /**
   * Test removal.
   */
  public function testRemove(): void {
    $data_tree = new SourceTree();
    $id = $data_tree->attachToRoot(0, 'textfield', []);
    self::assertCount(1, $data_tree->getTree());

    self::assertTrue($data_tree->remove($id));
    self::assertCount(0, $data_tree->getTree());
  }

  /**
   * Test the specific scenario described by the user (complex nesting).
   */
  public function testComplexScenario(): void {
    $data_tree = new SourceTree();

    // Root
    // - A (component)
    //   - slot_1
    //     - B (component)
    //       - slot_1
    //         - C (textfield)
    //         - D (textfield)
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'B']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);
    $node_d = $data_tree->attachToSlot($node_b, 'slot_1', 1, 'textfield', ['value' => 'D']);

    // Move C to A's slot_1 position 1.
    self::assertTrue($data_tree->moveToSlot($node_c, $node_a, 'slot_1', 1));

    // Move D to A's slot_1 position 2.
    self::assertTrue($data_tree->moveToSlot($node_d, $node_a, 'slot_1', 2));

    // Move B to root position 1.
    self::assertTrue($data_tree->moveToRoot($node_b, 1));

    $tree = $data_tree->getTree();
    self::assertCount(2, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);
    self::assertSame($node_b, $tree[1]['node_id']);

    $slot_a = $tree[0]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(2, $slot_a);
    self::assertContains($node_c, \array_column($slot_a, 'node_id'));
    self::assertContains($node_d, \array_column($slot_a, 'node_id'));
    self::assertEmpty($tree[1]['source']['component']['slots']['slot_1']['sources']);
  }

  /**
   * Test getParentId.
   */
  public function testGetParentId(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'textfield', ['value' => 'B']);

    self::assertNull($data_tree->getParentId($node_a));
    self::assertSame($node_a, $data_tree->getParentId($node_b));
  }

  /**
   * Test setSource.
   */
  public function testSetSource(): void {
    $data_tree = new SourceTree();
    $id = $data_tree->attachToRoot(0, 'textfield', ['value' => 'old']);
    self::assertTrue($data_tree->setSource($id, 'textfield', ['value' => 'new']));
    self::assertSame('new', $data_tree->getNode($id)['source']['value']);
  }

  /**
   * Test setThirdPartySettings.
   */
  public function testSetThirdPartySettings(): void {
    $data_tree = new SourceTree();
    $id = $data_tree->attachToRoot(0, 'textfield', []);
    self::assertTrue($data_tree->setThirdPartySettings($id, 'my_island', ['foo' => 'bar']));
    self::assertSame(['foo' => 'bar'], $data_tree->getNode($id)['third_party_settings']['my_island']);
  }

  /**
   * Test deep nesting of components (at least 3 levels).
   */
  public function testDeepNesting(): void {
    $data_tree = new SourceTree();

    // Level 1: Root Component A.
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);

    // Level 2: Component B in A's slot_1.
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'B']]);

    // Level 3: Component C in B's slot_1.
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'component', ['component' => ['component_id' => 'C']]);

    // Level 4: Textfield D in C's slot_1.
    $node_d = $data_tree->attachToSlot($node_c, 'slot_1', 0, 'textfield', ['value' => 'D']);

    $tree = $data_tree->getTree();

    // Verify nesting structure.
    self::assertCount(1, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);

    $level2 = $tree[0]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(1, $level2);
    self::assertSame($node_b, $level2[0]['node_id']);

    $level3 = $level2[0]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(1, $level3);
    self::assertSame($node_c, $level3[0]['node_id']);

    $level4 = $level3[0]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(1, $level4);
    self::assertSame($node_d, $level4[0]['node_id']);
    self::assertSame('D', $level4[0]['source']['value']);

    // Test moving the whole branch (B) to root.
    self::assertTrue($data_tree->moveToRoot($node_b, 1));
    $tree_moved = $data_tree->getTree();

    self::assertCount(2, $tree_moved);
    self::assertSame($node_a, $tree_moved[0]['node_id']);
    self::assertSame($node_b, $tree_moved[1]['node_id']);
    self::assertEmpty($tree_moved[0]['source']['component']['slots']['slot_1']['sources']);

    // Verify B still has its descendants.
    $b_level3 = $tree_moved[1]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(1, $b_level3);
    self::assertSame($node_c, $b_level3[0]['node_id']);

    // Test moving deeply nested C to root.
    self::assertTrue($data_tree->moveToRoot($node_c, 2));
    $tree_moved_c = $data_tree->getTree();

    self::assertCount(3, $tree_moved_c);
    self::assertSame($node_a, $tree_moved_c[0]['node_id']);
    self::assertSame($node_b, $tree_moved_c[1]['node_id']);
    self::assertSame($node_c, $tree_moved_c[2]['node_id']);

    // Verify C still has its descendant D.
    $c_level2 = $tree_moved_c[2]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(1, $c_level2);
    self::assertSame($node_d, $c_level2[0]['node_id']);

    // Verify B is now empty.
    self::assertEmpty($tree_moved_c[1]['source']['component']['slots']['slot_1']['sources']);
  }

  /**
   * Test normalization and denormalization specifically.
   */
  public function testNormalization(): void {
    $initial_tree = [
      [
        'node_id' => 'root_1',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'comp_A',
            'slots' => [
              'slot_1' => [
                'sources' => [
                  [
                    'node_id' => 'child_1',
                    'source_id' => 'textfield',
                    'source' => ['value' => 'val_1'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    // We use a subclass to access protected properties/methods for the test.
    // phpcs:disable Drupal.Commenting.FunctionComment.Missing
    $tree = new class($initial_tree) extends SourceTree {

      public function getNodes(): array {
        return $this->nodes;
      }

      public function getStructure(): array {
        return $this->structure;
      }

      public function getRootIds(): array {
        return $this->root;
      }

      public function callNormalize(array $items, ?string $parent_id): array {
        return $this->normalize($items, $parent_id);
      }

      public function callDenormalize(array $ids): array {
        return $this->denormalize($ids);
      }

    };
    // phpcs:enable Drupal.Commenting.FunctionComment.Missing

    // 1. Verify internal state after constructor normalization.
    $nodes = $tree->getNodes();
    $structure = $tree->getStructure();
    $rootIds = $tree->getRootIds();

    self::assertCount(2, $nodes);
    self::assertArrayHasKey('root_1', $nodes);
    self::assertArrayHasKey('child_1', $nodes);

    // Verify children are stripped from node data.
    self::assertArrayNotHasKey('sources', $nodes['root_1']['source']['component']['slots']['slot_1']);

    // Verify structure.
    self::assertSame(['root_1'], $rootIds);
    self::assertNull($structure['root_1']['parent']);
    self::assertSame('root_1', $structure['child_1']['parent']);
    self::assertSame(['child_1'], $structure['root_1']['slots']['slot_1']);

    // 2. Verify denormalization.
    $denormalized = $tree->callDenormalize($rootIds);
    self::assertSame($initial_tree, $denormalized);
  }

  /**
   * Test circular move prevention.
   */
  public function testCircularMovePreventionParentToDescendant(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'B']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'component', ['component' => ['component_id' => 'C']]);

    // Attempt to move A (parent) into C's slot (which is a descendant of A).
    // This should fail because A is an ancestor of C.
    self::assertFalse($data_tree->moveToSlot($node_a, $node_c, 'slot_1', 0));

    // Tree structure should remain unchanged.
    $tree = $data_tree->getTree();
    self::assertCount(1, $tree);
    self::assertCount(1, $tree[0]['source']['component']['slots']['slot_1']['sources']);
  }

  /**
   * Test circular move prevention at multiple levels.
   */
  public function testCircularMovePreventionMultipleLevels(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'B']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'component', ['component' => ['component_id' => 'C']]);
    $node_d = $data_tree->attachToSlot($node_c, 'slot_1', 0, 'textfield', ['value' => 'D']);

    // Try to move B (ancestor of C and D) into D's slot.
    self::assertFalse($data_tree->moveToSlot($node_b, $node_d, 'slot_1', 0));

    // Verify structure is unchanged.
    $tree = $data_tree->getTree();
    $level2 = $tree[0]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(1, $level2);
    self::assertSame($node_b, $level2[0]['node_id']);
  }

  /**
   * Test attachToSlot with invalid parent ID.
   */
  public function testAttachToSlotWithInvalidParent(): void {
    $data_tree = new SourceTree();
    $result = $data_tree->attachToSlot('non_existent_parent', 'slot_1', 0, 'textfield', ['value' => 'test']);
    self::assertNull($result);

    // Tree should remain empty.
    self::assertCount(0, $data_tree->getTree());
  }

  /**
   * Test moveToRoot with non-existent node.
   */
  public function testMoveToRootWithNonExistentNode(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'textfield', ['value' => 'A']);

    self::assertFalse($data_tree->moveToRoot('non_existent_node', 1));

    // Tree should be unchanged.
    $tree = $data_tree->getTree();
    self::assertCount(1, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);
  }

  /**
   * Test moveToSlot with non-existent node.
   */
  public function testMoveToSlotWithNonExistentNode(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);

    self::assertFalse($data_tree->moveToSlot('non_existent_node', $node_a, 'slot_1', 0));

    // Tree should be unchanged.
    self::assertCount(1, $data_tree->getTree());
  }

  /**
   * Test moveToSlot with non-existent parent.
   */
  public function testMoveToSlotWithNonExistentParent(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'textfield', ['value' => 'A']);

    self::assertFalse($data_tree->moveToSlot($node_a, 'non_existent_parent', 'slot_1', 0));

    // Node A should still be at root.
    $tree = $data_tree->getTree();
    self::assertCount(1, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);
  }

  /**
   * Test remove with non-existent node.
   */
  public function testRemoveNonExistentNode(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'textfield', ['value' => 'A']);

    self::assertFalse($data_tree->remove('non_existent_node'));

    // Tree should be unchanged.
    $tree = $data_tree->getTree();
    self::assertCount(1, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);
  }

  /**
   * Test getNode with non-existent node.
   */
  public function testGetNodeNonExistent(): void {
    $data_tree = new SourceTree();
    self::assertNull($data_tree->getNode('non_existent_node'));
  }

  /**
   * Test setSource with non-existent node.
   */
  public function testSetSourceNonExistent(): void {
    $data_tree = new SourceTree();
    self::assertFalse($data_tree->setSource('non_existent_node', 'textfield', ['value' => 'new']));
  }

  /**
   * Test setThirdPartySettings with non-existent node.
   */
  public function testSetThirdPartySettingsNonExistent(): void {
    $data_tree = new SourceTree();
    self::assertFalse($data_tree->setThirdPartySettings('non_existent_node', 'island', ['data' => 'value']));
  }

  /**
   * Test getParentId with non-existent node.
   */
  public function testGetParentIdNonExistent(): void {
    $data_tree = new SourceTree();
    self::assertNull($data_tree->getParentId('non_existent_node'));
  }

  /**
   * Test boundary condition: attach at position 0 (insert at beginning).
   */
  public function testAttachToRootAtBeginning(): void {
    $data_tree = new SourceTree();
    $id1 = $data_tree->attachToRoot(0, 'textfield', ['value' => '1']);
    $id2 = $data_tree->attachToRoot(0, 'textfield', ['value' => '2']);
    $id3 = $data_tree->attachToRoot(0, 'textfield', ['value' => '3']);

    $tree = $data_tree->getTree();
    self::assertCount(3, $tree);
    self::assertSame($id3, $tree[0]['node_id']);
    self::assertSame($id2, $tree[1]['node_id']);
    self::assertSame($id1, $tree[2]['node_id']);
  }

  /**
   * Test boundary condition: attach at high position (append).
   */
  public function testAttachToRootAtEnd(): void {
    $data_tree = new SourceTree();
    $id1 = $data_tree->attachToRoot(0, 'textfield', ['value' => '1']);
    $id2 = $data_tree->attachToRoot(999, 'textfield', ['value' => '2']);

    $tree = $data_tree->getTree();
    self::assertCount(2, $tree);
    self::assertSame($id1, $tree[0]['node_id']);
    self::assertSame($id2, $tree[1]['node_id']);
  }

  /**
   * Test multiple attachments to different slots of same parent.
   */
  public function testMultipleSlotsOnParent(): void {
    $data_tree = new SourceTree();
    $parent = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'parent']]);

    $slot1_child1 = $data_tree->attachToSlot($parent, 'slot_1', 0, 'textfield', ['value' => 'slot1_child1']);
    $slot1_child2 = $data_tree->attachToSlot($parent, 'slot_1', 1, 'textfield', ['value' => 'slot1_child2']);
    $slot2_child1 = $data_tree->attachToSlot($parent, 'slot_2', 0, 'textfield', ['value' => 'slot2_child1']);

    $tree = $data_tree->getTree();
    $slot_1 = $tree[0]['source']['component']['slots']['slot_1']['sources'];
    $slot_2 = $tree[0]['source']['component']['slots']['slot_2']['sources'];

    self::assertCount(2, $slot_1);
    self::assertCount(1, $slot_2);
    self::assertSame($slot1_child1, $slot_1[0]['node_id']);
    self::assertSame($slot1_child2, $slot_1[1]['node_id']);
    self::assertSame($slot2_child1, $slot_2[0]['node_id']);
  }

  /**
   * Test path index caching and invalidation on mutations.
   */
  public function testPathIndexCaching(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'textfield', ['value' => 'B']);

    // Get path index first time (should compute).
    $index1 = $data_tree->getPathIndex();
    self::assertArrayHasKey($node_a, $index1);
    self::assertArrayHasKey($node_b, $index1);

    // Get path index second time (should use cache).
    $index2 = $data_tree->getPathIndex();
    self::assertSame($index1, $index2);

    // After a mutation, cache should be invalidated.
    $data_tree->attachToRoot(1, 'textfield', ['value' => 'C']);
    $index3 = $data_tree->getPathIndex();
    self::assertNotSame($index1, $index3);
    self::assertCount(3, $index3);
  }

  /**
   * Test path index accuracy.
   */
  public function testPathIndexAccuracy(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'B']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);

    $index = $data_tree->getPathIndex();

    // Verify path for root node.
    self::assertSame([0], $index[$node_a]['path']);
    self::assertNull($index[$node_a]['parent']);

    // Verify path for first-level nested.
    self::assertSame([0, 'source', 'component', 'slots', 'slot_1', 'sources', 0], $index[$node_b]['path']);
    self::assertSame($node_a, $index[$node_b]['parent']);

    // Verify path for deeply nested.
    self::assertSame([0, 'source', 'component', 'slots', 'slot_1', 'sources', 0, 'source', 'component', 'slots', 'slot_1', 'sources', 0], $index[$node_c]['path']);
    self::assertSame($node_b, $index[$node_c]['parent']);
  }

  /**
   * Test remove preserves sibling order.
   */
  public function testRemovePreservesSiblingOrder(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'textfield', ['value' => 'A']);
    $node_b = $data_tree->attachToRoot(1, 'textfield', ['value' => 'B']);
    $node_c = $data_tree->attachToRoot(2, 'textfield', ['value' => 'C']);

    // Remove middle node.
    self::assertTrue($data_tree->remove($node_b));

    $tree = $data_tree->getTree();
    self::assertCount(2, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);
    self::assertSame($node_c, $tree[1]['node_id']);
  }

  /**
   * Test recursive removal of descendants.
   */
  public function testRecursiveRemove(): void {
    $data_tree = new SourceTree();
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'A']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'B']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);
    $node_d = $data_tree->attachToSlot($node_a, 'slot_2', 0, 'textfield', ['value' => 'D']);

    // Remove node A (should also remove B and C).
    self::assertTrue($data_tree->remove($node_a));

    $tree = $data_tree->getTree();
    self::assertCount(0, $tree);

    // Verify all nodes are removed.
    self::assertNull($data_tree->getNode($node_a));
    self::assertNull($data_tree->getNode($node_b));
    self::assertNull($data_tree->getNode($node_c));
    self::assertNull($data_tree->getNode($node_d));
  }

  /**
   * Test setSource and setThirdPartySettings work correctly.
   */
  public function testSetSourceAndSettings(): void {
    $data_tree = new SourceTree();
    $node_id = $data_tree->attachToRoot(0, 'textfield', ['value' => 'old']);

    self::assertTrue($data_tree->setSource($node_id, 'textfield', ['value' => 'new']));
    self::assertTrue($data_tree->setThirdPartySettings($node_id, 'island_1', ['option' => 'value']));

    $node = $data_tree->getNode($node_id);
    self::assertSame('new', $node['source']['value']);
    self::assertSame(['option' => 'value'], $node['third_party_settings']['island_1']);

    // Update settings again.
    self::assertTrue($data_tree->setThirdPartySettings($node_id, 'island_2', ['other' => 'data']));
    $node = $data_tree->getNode($node_id);
    self::assertCount(2, $node['third_party_settings']);
    self::assertSame(['option' => 'value'], $node['third_party_settings']['island_1']);
    self::assertSame(['other' => 'data'], $node['third_party_settings']['island_2']);
  }

  /**
   * Test move to same parent different slot and position.
   */
  public function testMoveWithinSameParent(): void {
    $data_tree = new SourceTree();
    $parent = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'parent']]);
    $child1 = $data_tree->attachToSlot($parent, 'slot_1', 0, 'textfield', ['value' => '1']);
    $child2 = $data_tree->attachToSlot($parent, 'slot_1', 1, 'textfield', ['value' => '2']);
    $child3 = $data_tree->attachToSlot($parent, 'slot_1', 2, 'textfield', ['value' => '3']);

    // Move child1 to position 2 (between child2 and child3).
    self::assertTrue($data_tree->moveToSlot($child1, $parent, 'slot_1', 2));

    $tree = $data_tree->getTree();
    $slot = $tree[0]['source']['component']['slots']['slot_1']['sources'];
    self::assertCount(3, $slot);
    self::assertSame($child2, $slot[0]['node_id']);
    self::assertSame($child3, $slot[1]['node_id']);
    self::assertSame($child1, $slot[2]['node_id']);
  }

  /**
   * Test move between slots.
   */
  public function testMoveBetweenSlots(): void {
    $data_tree = new SourceTree();
    $parent = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'parent']]);
    $child = $data_tree->attachToSlot($parent, 'slot_1', 0, 'textfield', ['value' => 'child']);

    // Move from slot_1 to slot_2.
    self::assertTrue($data_tree->moveToSlot($child, $parent, 'slot_2', 0));

    $tree = $data_tree->getTree();
    self::assertEmpty($tree[0]['source']['component']['slots']['slot_1']['sources']);
    self::assertCount(1, $tree[0]['source']['component']['slots']['slot_2']['sources']);
    self::assertSame($child, $tree[0]['source']['component']['slots']['slot_2']['sources'][0]['node_id']);
  }

}
