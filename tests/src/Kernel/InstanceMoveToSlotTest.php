<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the history functionality of the Instance entity.
 *
 * @internal
 */
#[CoversClass(Instance::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class InstanceMoveToSlotTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'layout_discovery',
    'display_builder',
    'display_builder_ui',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder']);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveToEmptySlotOfSameComponent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];

    // Move to an empty slot.
    $state = $this->getInitialDataTree([], [], [], [$source_to_move]);
    $instance->setNewPresent($state, '');
    $instance->remove('inner_layout');
    $instance->moveToSlot('source_1', 'upper_component', 'slot_2', 0);
    $state = $instance->getCurrentState();

    self::assertEmpty($this->getComponentSlot($state[1], 'slot_1'));
    self::assertSame($this->getComponentSlot($state[1], 'slot_2'), [$source_to_move]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0],
      'parent' => 'upper_component',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveToBusySlotOfSameComponent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];

    // Move to the beginning of an busy slot.
    $state = $this->getInitialDataTree([], [], [], [$source_to_move]);
    $instance->setNewPresent($state, '');
    $instance->moveToSlot('source_1', 'upper_component', 'slot_2', 0);
    $state = $instance->getCurrentState();

    self::assertEmpty($this->getComponentSlot($state[1], 'slot_1'));
    self::assertSame($this->getComponentSlot($state[1], 'slot_2')[0], $source_to_move);
    self::assertIsArray($this->getComponentSlot($state[1], 'slot_2')[1]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0],
      'parent' => 'upper_component',
    ]);

    // Move to the end of an busy slot.
    $instance->setNewPresent($state, '');
    $instance->moveToSlot('source_1', 'upper_component', 'slot_2', 1);
    $state = $instance->getCurrentState();

    self::assertEmpty($this->getComponentSlot($state[1], 'slot_1'));
    self::assertIsArray($this->getComponentSlot($state[1], 'slot_2')[0]);
    self::assertSame($this->getComponentSlot($state[1], 'slot_2')[1], $source_to_move);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 1],
      'parent' => 'upper_component',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveToSameSlotOfSameComponent(): void {
    $instance = $this->createDisplayBuilderInstance();

    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];
    $other_source_1 = [
      'node_id' => 'source_2',
      'source_id' => 'textfield',
    ];
    $other_source_2 = [
      'node_id' => 'source_3',
      'source_id' => 'textfield',
    ];

    $state = $this->getInitialDataTree([], [], [], [$source_to_move, $other_source_1, $other_source_2]);
    $instance->setNewPresent($state, '');

    // Move at the beginning (same position) of the slot.
    $instance->moveToSlot('source_1', 'upper_component', 'slot_1', 0);
    $state = $instance->getCurrentState();
    self::assertSame($this->getComponentSlot($state[1], 'slot_1'), [$source_to_move, $other_source_1, $other_source_2]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_1', 'sources', 0],
      'parent' => 'upper_component',
    ]);

    // Move at the middle position of the slot.
    $instance->moveToSlot('source_1', 'upper_component', 'slot_1', 1);
    $state = $instance->getCurrentState();
    self::assertSame($this->getComponentSlot($state[1], 'slot_1'), [$other_source_1, $source_to_move, $other_source_2]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_1', 'sources', 1],
      'parent' => 'upper_component',
    ]);

    // Move at the middle position of the slot.
    $instance->moveToSlot('source_1', 'upper_component', 'slot_1', 2);
    $state = $instance->getCurrentState();
    self::assertSame($this->getComponentSlot($state[1], 'slot_1'), [$other_source_1, $other_source_2, $source_to_move]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_1', 'sources', 2],
      'parent' => 'upper_component',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveFromLayoutToComponent(): void {
    $instance = $this->createDisplayBuilderInstance();

    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];

    // Move to an empty slot.
    $state = $this->getInitialDataTree([], [], [$source_to_move], []);
    $instance->setNewPresent($state, '');
    $instance->moveToSlot('source_1', 'upper_component', 'slot_1', 0);
    $state = $instance->getCurrentState();
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertEmpty($this->getLayoutRegion($layout, 'content'));
    self::assertSame($this->getComponentSlot($state[1], 'slot_1'), [$source_to_move]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_1', 'sources', 0],
      'parent' => 'upper_component',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveToSameRegionOfSameLayout(): void {
    $instance = $this->createDisplayBuilderInstance();

    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];
    $other_source_1 = [
      'node_id' => 'source_2',
      'source_id' => 'textfield',
    ];
    $other_source_2 = [
      'node_id' => 'source_3',
      'source_id' => 'textfield',
    ];

    $state = $this->getInitialDataTree([], [], [$source_to_move, $other_source_1, $other_source_2], []);
    $instance->setNewPresent($state, '');

    // Move at the beginning (same position) of the region.
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 0);
    $state = $instance->getCurrentState();
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertSame($this->getLayoutRegion($layout, 'content'), [$source_to_move, $other_source_1, $other_source_2]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 0],
      'parent' => 'inner_layout',
    ]);

    // Move at the middle position of the region.
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 1);
    $state = $instance->getCurrentState();
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertSame($this->getLayoutRegion($layout, 'content'), [$other_source_1, $source_to_move, $other_source_2]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 1],
      'parent' => 'inner_layout',
    ]);

    // Move at the middle position of the region.
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 2);
    $state = $instance->getCurrentState();
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertSame($this->getLayoutRegion($layout, 'content'), [$other_source_1, $other_source_2, $source_to_move]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 2],
      'parent' => 'inner_layout',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveFromComponentToEmptyLayoutRegion(): void {
    $instance = $this->createDisplayBuilderInstance();

    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];

    $state = $this->getInitialDataTree([], [], [], [$source_to_move]);
    $instance->setNewPresent($state, '');
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 0);
    $state = $instance->getCurrentState();
    self::assertEmpty($this->getComponentSlot($state[1], 'slot_1'));
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertSame($this->getLayoutRegion($layout, 'content'), [$source_to_move]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 0],
      'parent' => 'inner_layout',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveFromComponentToNestedLayoutEmptyRegion(): void {
    $instance = $this->createDisplayBuilderInstance();

    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];

    $state = $this->getInitialDataTree([], [], [], [$source_to_move]);
    $instance->setNewPresent($state, '');
    // We first put the source to move in the same slot as the nested layout.
    $instance->moveToSlot('source_1', 'upper_component', 'slot_2', 0);
    // `inner_layout` is now the second source of the slot.
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 0);
    $state = $instance->getCurrentState();
    // `inner_layout` is the only source of the slot but it is indexed "1"
    // instead of  "0".
    // We consider this OK because we want to keep consistent indexes
    // during the chain of atomic operation and we expect the values to
    // be correctly indexed at the very end, thanks to ::buildIndexFromSlot().
    $inner_layout = $this->getComponentSlot($state[1], 'slot_2')[1];

    self::assertNotEmpty($inner_layout);
    self::assertSame($this->getLayoutRegion($inner_layout, 'content'), [$source_to_move]);
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 0],
      'parent' => 'inner_layout',
    ]);
  }

  /**
   * Test the ::moveToSlot() method.
   */
  public function testMoveFromComponentToBusyLayoutRegion(): void {
    $instance = $this->createDisplayBuilderInstance();

    $source_to_move = [
      'node_id' => 'source_1',
      'source_id' => 'textfield',
    ];
    $other_source_1 = [
      'node_id' => 'source_2',
      'source_id' => 'textfield',
    ];
    $other_source_2 = [
      'node_id' => 'source_3',
      'source_id' => 'textfield',
    ];

    // Move to the beginning of an busy slot.
    $state = $this->getInitialDataTree([$source_to_move], [], [$other_source_1, $other_source_2], []);
    $instance->setNewPresent($state, '');
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 0);
    $state = $instance->getCurrentState();
    self::assertEmpty($this->getComponentSlot($state[1], 'slot_1'));
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertSame(
      $this->getLayoutRegion($layout, 'content'),
      [$source_to_move, $other_source_1, $other_source_2]
    );
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 0],
      'parent' => 'inner_layout',
    ]);

    // Move to the end of an busy slot.
    $instance->moveToSlot('source_1', 'inner_layout', 'content', 2);
    $state = $instance->getCurrentState();
    $layout = $this->getComponentSlot($state[1], 'slot_2')[0];
    self::assertSame(
      $this->getLayoutRegion($layout, 'content'),
      [$other_source_1, $other_source_2, $source_to_move]
    );
    self::assertSame($instance->getPathIndex()['source_1'], [
      'path' => [1, 'source', 'component', 'slots', 'slot_2', 'sources', 0, 'source', 'regions', 'content', 2],
      'parent' => 'inner_layout',
    ]);
  }

  /**
   * Get component slot value without using Instance methods.
   */
  protected function getComponentSlot(array $component, string $slot_id): array {
    return $component['source']['component']['slots'][$slot_id]['sources'];
  }

  /**
   * Get layout region value without using Instance methods.
   */
  protected function getLayoutRegion(array $component, string $slot_id): array {
    return $component['source']['regions'][$slot_id];
  }

  /**
   * Get the initial data tree for tests.
   */
  protected function getInitialDataTree(array $slot_1 = [], array $slot_2 = [], array $slot_3 = [], array $slot_4 = []): array {
    return [
      [
        'node_id' => 'upper_layout',
        'source_id' => 'layout',
        'source' => [
          'plugin_id' => 'layout_onecol',
          'regions' => [
            'content' => [
              [
                'node_id' => 'inner_component',
                'source_id' => 'component',
                'source' => [
                  'component' => [
                    'component_id' => 'display_builder_test:test_2',
                    'slots' => [
                      'slot_1' => [
                        'sources' => $slot_1,
                      ],
                      'slot_2' => [
                        'sources' => $slot_2,
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      [
        'node_id' => 'upper_component',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_2',
            'slots' => [
              'slot_1' => [
                'sources' => $slot_4,
              ],
              'slot_2' => [
                'sources' => [
                  [
                    'node_id' => 'inner_layout',
                    'source_id' => 'layout',
                    'source' => [
                      'plugin_id' => 'layout_onecol',
                      'regions' => [
                        'content' => $slot_3,
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
