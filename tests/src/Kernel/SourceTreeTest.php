<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\display_builder\SourceTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the SourceTree class.
 *
 * @internal
 */
#[CoversClass(SourceTree::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class SourceTreeTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path_alias',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
    'layout_discovery',
    'display_builder_page_layout',
    'block',
  ];

  /**
   * The plugin source manager.
   */
  protected PluginManagerInterface $sourceManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'display_builder', 'display_builder_test', 'ui_patterns']);

    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');
  }

  /**
   * Test basic initialization and indexing.
   */
  public function testInitialization(): void {
    $initial_tree = [
      [
        'node_id' => 'node_1',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_1',
            'slots' => [
              'slot_1' => [
                'sources' => [
                  [
                    'node_id' => 'node_1_child',
                    'source_id' => 'textfield',
                    'source' => ['value' => 'B'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      [
        'node_id' => 'node_2',
        'source_id' => 'textfield',
        'source' => ['value' => 'A'],
      ],
    ];

    $data_tree = new SourceTree($initial_tree, $this->sourceManager);
    $tree = $data_tree->getTree();

    self::assertEquals($initial_tree, $tree);
  }

  /**
   * Test with layout sources.
   */
  public function testLayoutSources(): void {
    $initial_tree = [
      [
        'node_id' => 'layout_node',
        'source_id' => 'layout',
        'source' => [
          'layout_id' => 'layout_onecol',
          'regions' => [
            'content' => [
              [
                'node_id' => 'inside_layout',
                'source_id' => 'textfield',
                'source' => ['value' => 'Inside Layout'],
              ],
            ],
          ],
        ],
      ],
    ];

    $data_tree = new SourceTree($initial_tree, $this->sourceManager);
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
        'node_id' => 'page_layout_node',
        'source_id' => 'page_layout',
        'source' => [
          'regions' => [
            'header' => [
              [
                'node_id' => 'header_child',
                'source_id' => 'textfield',
                'source' => ['value' => 'Header Content'],
              ],
            ],
          ],
        ],
      ],
    ];

    $data_tree = new SourceTree($initial_tree, $this->sourceManager);
    $tree = $data_tree->getTree();

    self::assertCount(1, $tree);
    self::assertSame('page_layout', $tree[0]['source_id']);
    self::assertNotEmpty($tree[0]['source']['regions']['header']);
    self::assertSame('Header Content', $tree[0]['source']['regions']['header'][0]['source']['value']);
  }

  /**
   * Test attachToRoot.
   */
  public function testAttachToRoot(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
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
    $data_tree = new SourceTree([], $this->sourceManager);
    $parent_id = $data_tree->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
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
    $data_tree = new SourceTree([], $this->sourceManager);
    $parent_id = $data_tree->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $child_id = $data_tree->attachToSlot($parent_id, 'slot_1', 0, 'textfield', ['value' => 'child']);

    self::assertTrue($data_tree->moveToRoot($child_id, 1));

    $tree = $data_tree->getTree();
    self::assertCount(2, $tree);
    self::assertSame($parent_id, $tree[0]['node_id']);
    self::assertSame($child_id, $tree[1]['node_id']);
    self::assertEmpty($tree[0]['source']['component']['slots']['slot_1']['sources'] ?? []);
  }

  /**
   * Test moveToSlot.
   */
  public function testMoveToSlot(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $node_b = $data_tree->attachToRoot(1, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_2'],
    ]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);

    // Move C from B to A.
    self::assertTrue($data_tree->moveToSlot($node_c, $node_a, 'slot_1', 0));

    $tree = $data_tree->getTree();
    self::assertSame($node_c, $tree[0]['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
    self::assertEmpty($tree[1]['source']['component']['slots']['slot_1']['sources'] ?? []);
  }

  /**
   * Test removal.
   */
  public function testRemove(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $id = $data_tree->attachToRoot(0, 'textfield', []);
    self::assertCount(1, $data_tree->getTree());

    self::assertTrue($data_tree->remove($id));
    self::assertCount(0, $data_tree->getTree());
  }

  /**
   * Test complex scenario.
   */
  public function testComplexScenario(): void {
    $data_tree = new SourceTree([], $this->sourceManager);

    // Root
    // - A (component)
    //   - slot_1
    //     - B (component)
    //       - slot_1
    //         - C (textfield)
    //         - D (textfield)
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_2']]);
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

    self::assertCount(2, $tree[0]['source']['component']['slots']['slot_1']['sources']);
    self::assertSame($node_c, $tree[0]['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
    self::assertSame($node_d, $tree[0]['source']['component']['slots']['slot_1']['sources'][1]['node_id']);
    self::assertEmpty($tree[1]['source']['component']['slots']['slot_1']['sources'] ?? []);
  }

  /**
   * Test getParentId.
   */
  public function testGetParentId(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'textfield', ['value' => 'B']);

    self::assertNull($data_tree->getParentId($node_a));
    self::assertSame($node_a, $data_tree->getParentId($node_b));
  }

  /**
   * Test setSource.
   */
  public function testSetSource(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $id = $data_tree->attachToRoot(0, 'textfield', ['value' => 'old']);
    self::assertTrue($data_tree->setSource($id, 'textfield', ['value' => 'new']));
    self::assertSame('new', $data_tree->getNode($id)['source']['value']);
  }

  /**
   * Test setThirdPartySettings.
   */
  public function testSetThirdPartySettings(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $id = $data_tree->attachToRoot(0, 'textfield', []);
    self::assertTrue($data_tree->setThirdPartySettings($id, 'my_island', ['foo' => 'bar']));
    self::assertSame(['foo' => 'bar'], $data_tree->getNode($id)['third_party_settings']['my_island']);
  }

  /**
   * Test deep nesting.
   */
  public function testDeepNesting(): void {
    $data_tree = new SourceTree([], $this->sourceManager);

    // Level 1: Root Component A.
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    // Level 2: Component B in A's slot_1.
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_2']]);
    // Level 3: Component C in B's slot_1.
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    // Level 4: Textfield D in C's slot_1.
    $node_d = $data_tree->attachToSlot($node_c, 'slot_1', 0, 'textfield', ['value' => 'D']);

    $tree = $data_tree->getTree();
    self::assertCount(1, $tree);
    self::assertSame($node_d, $tree[0]['source']['component']['slots']['slot_1']['sources'][0]['source']['component']['slots']['slot_1']['sources'][0]['source']['component']['slots']['slot_1']['sources'][0]['node_id']);
  }

  /**
   * Test normalization and denormalization.
   */
  public function testNormalization(): void {
    $initial_tree = [
      [
        'node_id' => 'root_1',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_1',
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

    $tree = new SourceTree($initial_tree, $this->sourceManager);
    $denormalized = $tree->getTree();
    self::assertSame($initial_tree, $denormalized);
  }

  /**
   * Test hasNode.
   */
  public function testHasNode(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    self::assertFalse($data_tree->hasNode('nonexistent'));

    $id = $data_tree->attachToRoot(0, 'textfield', []);
    self::assertTrue($data_tree->hasNode($id));

    $data_tree->remove($id);
    self::assertFalse($data_tree->hasNode($id));
  }

  /**
   * Test getNodeData returns flat data without children.
   */
  public function testGetNodeData(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    self::assertNull($data_tree->getNodeData('nonexistent'));

    $id = $data_tree->attachToRoot(0, 'textfield', ['value' => 'hello']);
    $data = $data_tree->getNodeData($id);
    self::assertNotNull($data);
    self::assertSame('textfield', $data['source_id']);
    self::assertSame('hello', $data['source']['value']);

    // getNodeData must not include nested children even for slot-bearing nodes.
    $parent_id = $data_tree->attachToRoot(1, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $data_tree->attachToSlot($parent_id, 'slot_1', 0, 'textfield', ['value' => 'child']);
    $flat = $data_tree->getNodeData($parent_id);
    self::assertArrayNotHasKey('slots', $flat['source']['component'] ?? []);
  }

  /**
   * Test remove cascades to descendants.
   */
  public function testRemoveCascadesDescendants(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_2']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);

    self::assertTrue($data_tree->remove($node_a));

    // All three nodes must be gone.
    self::assertFalse($data_tree->hasNode($node_a));
    self::assertFalse($data_tree->hasNode($node_b));
    self::assertFalse($data_tree->hasNode($node_c));
    self::assertCount(0, $data_tree->getTree());
  }

  /**
   * Test moveToSlot is blocked when target is a descendant of the moved node.
   */
  public function testMoveToSlotForbiddenIntoDescendant(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_2']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);

    // Moving A into C's slot would create a cycle.
    self::assertFalse($data_tree->moveToSlot($node_a, $node_c, 'slot_1', 0));

    // Tree must be unchanged.
    $tree = $data_tree->getTree();
    self::assertCount(1, $tree);
    self::assertSame($node_a, $tree[0]['node_id']);
  }

  /**
   * Test invalid ID handling across mutation methods.
   */
  public function testInvalidIdHandling(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $id = $data_tree->attachToRoot(0, 'textfield', []);

    self::assertNull($data_tree->attachToSlot('nonexistent', 'slot_1', 0, 'textfield', []));
    self::assertFalse($data_tree->moveToRoot('nonexistent', 0));
    self::assertFalse($data_tree->moveToSlot('nonexistent', $id, 'slot_1', 0));
    self::assertFalse($data_tree->moveToSlot($id, 'nonexistent', 'slot_1', 0));
    self::assertFalse($data_tree->remove('nonexistent'));
    self::assertFalse($data_tree->setSource('nonexistent', 'textfield', []));
    self::assertFalse($data_tree->setThirdPartySettings('nonexistent', 'island', []));
    self::assertNull($data_tree->getNode('nonexistent'));
    self::assertNull($data_tree->getParentId('nonexistent'));
  }

  /**
   * Test path index stays consistent after setSource (no structure change).
   */
  public function testPathIndexNotAffectedBySetSource(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'textfield', ['value' => 'original']);

    $before = $data_tree->getPathIndex();
    $data_tree->setSource($node_b, 'textfield', ['value' => 'updated']);
    $after = $data_tree->getPathIndex();

    // Paths must be identical since structure did not change.
    self::assertSame($before[$node_a]['path'], $after[$node_a]['path']);
    self::assertSame($before[$node_b]['path'], $after[$node_b]['path']);
    // Source data update must be reflected in the node itself.
    self::assertSame('updated', $data_tree->getNode($node_b)['source']['value']);
  }

  /**
   * Test path index updates correctly after moveToSlot.
   */
  public function testPathIndexAfterMove(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToRoot(1, 'component', ['component' => ['component_id' => 'display_builder_test:test_2']]);
    $node_c = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'textfield', ['value' => 'C']);

    // C starts in A's slot.
    $index_before = $data_tree->getPathIndex();
    self::assertSame([0, 'source', 'component', 'slots', 'slot_1', 'sources', 0], $index_before[$node_c]['path']);

    // Move C into B's slot_1.
    self::assertTrue($data_tree->moveToSlot($node_c, $node_b, 'slot_1', 0));

    $index_after = $data_tree->getPathIndex();
    self::assertSame([1, 'source', 'component', 'slots', 'slot_1', 'sources', 0], $index_after[$node_c]['path']);
    self::assertSame($node_b, $index_after[$node_c]['parent']);
  }

  /**
   * Test path index updates correctly after remove.
   */
  public function testPathIndexAfterRemove(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'textfield', ['value' => 'A']);
    $node_b = $data_tree->attachToRoot(1, 'textfield', ['value' => 'B']);
    $node_c = $data_tree->attachToRoot(2, 'textfield', ['value' => 'C']);

    $data_tree->remove($node_a);

    $index = $data_tree->getPathIndex();
    self::assertArrayNotHasKey($node_a, $index);
    // B and C shift left by one after A is removed.
    self::assertSame([0], $index[$node_b]['path']);
    self::assertSame([1], $index[$node_c]['path']);
  }

  /**
   * Test path index accuracy.
   */
  public function testPathIndexAccuracy(): void {
    $data_tree = new SourceTree([], $this->sourceManager);
    $node_a = $data_tree->attachToRoot(0, 'component', ['component' => ['component_id' => 'display_builder_test:test_1']]);
    $node_b = $data_tree->attachToSlot($node_a, 'slot_1', 0, 'component', ['component' => ['component_id' => 'display_builder_test:test_2']]);
    $node_c = $data_tree->attachToSlot($node_b, 'slot_1', 0, 'textfield', ['value' => 'C']);

    $index = $data_tree->getPathIndex();

    // Verify path for root node.
    self::assertSame([0], $index[$node_a]['path']);
    // Verify path for first-level nested.
    self::assertSame([0, 'source', 'component', 'slots', 'slot_1', 'sources', 0], $index[$node_b]['path']);
    // Verify path for deeply nested.
    self::assertSame([0, 'source', 'component', 'slots', 'slot_1', 'sources', 0, 'source', 'component', 'slots', 'slot_1', 'sources', 0], $index[$node_c]['path']);
  }

}
