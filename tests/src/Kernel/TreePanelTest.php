<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the TreePanel (Navigator) build output.
 *
 * Pins the attribute contract the Navigator exposes: a root dropzone flagged
 * data-db-root, each component a tree node stamped
 * data-testid="layer_<source>" + data-node-id, each slot a node carrying
 * data-slot-id and its parent's data-node-id, its dropzone stamped
 * data-testid="dropzone_<slot_id>", and each block a node keyed by
 * data-node-id. The drag-and-drop JavaScript and the e2e selectors ride on
 * these, and TreePanel (unlike its ScaffoldPanel sibling) had none.
 *
 * @internal
 */
#[CoversClass('\Drupal\display_builder\Plugin\display_builder\Island\TreePanel')]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class TreePanelTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
  }

  /**
   * The root node is a drop target flagged as the tree root.
   */
  public function testRootAttributes(): void {
    $build = $this->buildTree();

    self::assertSame('display_builder:panel_tree', $build['#component']);
    self::assertTrue($build['#attributes']['data-db-root']);
    self::assertSame('test_instance', $build['#attributes']['data-db-id']);
    self::assertArrayHasKey('data-empty-hint', $build['#attributes']);
  }

  /**
   * The component tree node is keyed by its node id and owned by this panel.
   *
   * Unlike WireframePanelBase, the Navigator does not stamp a
   * data-testid="layer_<source>" on component rows; it addresses them by
   * data-node-id + data-menu-type. Only the dropzone carries a testid.
   */
  public function testComponentNodeAttributes(): void {
    $build = $this->buildTree();
    $component = $this->findByAttribute($build, 'data-menu-type', 'component');

    self::assertNotNull($component, 'A component tree node is present.');
    self::assertSame('component_1', $component['#attributes']['data-node-id']);
    self::assertSame('tree', $component['#attributes']['data-island-id']);
    self::assertArrayHasKey('data-node-title', $component['#attributes']);
  }

  /**
   * The slot node carries its own id and its parent component's id.
   */
  public function testSlotNodeAttributes(): void {
    $build = $this->buildTree();
    $slot = $this->findByAttribute($build, 'data-menu-type', 'slot');

    self::assertNotNull($slot, 'A slot tree node is present.');
    self::assertSame('slot_1', $slot['#attributes']['data-slot-id']);
    // The parent component, so a drop knows what it lands in.
    self::assertSame('component_1', $slot['#attributes']['data-node-id']);
  }

  /**
   * The slot dropzone carries its dropzone_<slot_id> test id.
   */
  public function testSlotDropzoneTestid(): void {
    $build = $this->buildTree();
    $dropzone = $this->findByAttribute($build, 'data-testid', 'dropzone_slot_1');

    self::assertNotNull($dropzone, 'The slot exposes a dropzone.');
    self::assertSame('display_builder:dropzone', $dropzone['#component']);
    self::assertSame('test_instance', $dropzone['#attributes']['data-db-id']);
  }

  /**
   * A block nested in the slot is a tree node keyed by its own node id.
   */
  public function testNestedBlockAttributes(): void {
    $build = $this->buildTree();
    $block = $this->findByAttribute($build, 'data-menu-type', 'block');

    self::assertNotNull($block, 'A block tree node is present.');
    self::assertSame('block_1', $block['#attributes']['data-node-id']);
  }

  /**
   * Build the Navigator over a component holding one block in a slot.
   *
   * @return array
   *   The TreePanel renderable.
   */
  private function buildTree(): array {
    $instance = Instance::create([
      'id' => 'test_instance',
      'label' => 'Test Instance',
    ]);

    $data = [
      [
        'node_id' => 'component_1',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_1',
            'slots' => [
              'slot_1' => [
                'sources' => [
                  [
                    'node_id' => 'block_1',
                    'source_id' => 'textfield',
                    'source' => ['value' => 'I am in a slot'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    return $this->createIslandPlugin('tree')->build($instance, $data, []);
  }

  /**
   * Depth-first search for the first element carrying an attribute value.
   *
   * @param array $build
   *   A render array to walk.
   * @param string $key
   *   The attribute key to match.
   * @param string $value
   *   The attribute value to match.
   *
   * @return array|null
   *   The first matching element, or NULL.
   */
  private function findByAttribute(array $build, string $key, string $value): ?array {
    if (($build['#attributes'][$key] ?? NULL) === $value) {
      return $build;
    }

    foreach ($build as $child) {
      if (\is_array($child)) {
        $found = $this->findByAttribute($child, $key, $value);

        if ($found !== NULL) {
          return $found;
        }
      }
    }

    return NULL;
  }

}
