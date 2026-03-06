<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ::moveToSlot() method with specific cases.
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
   * Test the move to empty slot of same component.
   */
  public function testMoveToEmptySlotOfSameComponent(): void {
    $instance = $this->createDisplayBuilderInstance();

    // A has slot_1 with S1, and empty slot_2.
    $state = [
      [
        'node_id' => 'A',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_2',
            'slots' => [
              'slot_1' => ['sources' => [['node_id' => 'S1', 'source_id' => 'textfield', 'source' => []]]],
              'slot_2' => ['sources' => []],
            ],
          ],
        ],
      ],
    ];
    $instance->setNewPresent($state, '');

    $instance->moveToSlot('S1', 'A', 'slot_2', 0);
    $state = $instance->getCurrentState();

    self::assertEmpty($this->getComponentSlot($state[0], 'slot_1'));
    $slot_2 = $this->getComponentSlot($state[0], 'slot_2');
    self::assertCount(1, $slot_2);
    self::assertSame('S1', $slot_2[0]['node_id']);
  }

  /**
   * Test the move to busy slot of same component.
   */
  public function testMoveToBusySlotOfSameComponent(): void {
    $instance = $this->createDisplayBuilderInstance();

    // A has slot_1 with S1, slot_2 with S2.
    $state = [
      [
        'node_id' => 'A',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_2',
            'slots' => [
              'slot_1' => ['sources' => [['node_id' => 'S1', 'source_id' => 'textfield', 'source' => []]]],
              'slot_2' => ['sources' => [['node_id' => 'S2', 'source_id' => 'textfield', 'source' => []]]],
            ],
          ],
        ],
      ],
    ];
    $instance->setNewPresent($state, '');

    // Move S1 to beginning of slot_2.
    $instance->moveToSlot('S1', 'A', 'slot_2', 0);
    $state = $instance->getCurrentState();

    self::assertEmpty($this->getComponentSlot($state[0], 'slot_1'));
    $slot_2 = $this->getComponentSlot($state[0], 'slot_2');
    self::assertCount(2, $slot_2);
    self::assertSame('S1', $slot_2[0]['node_id']);
    self::assertSame('S2', $slot_2[1]['node_id']);
  }

  /**
   * Test the move within the same slot of same component.
   */
  public function testMoveToSameSlotOfSameComponent(): void {
    $instance = $this->createDisplayBuilderInstance();

    // A has slot_1 with [S1, S2, S3].
    $state = [
      [
        'node_id' => 'A',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_2',
            'slots' => [
              'slot_1' => [
                'sources' => [
                  ['node_id' => 'S1', 'source_id' => 'textfield', 'source' => []],
                  ['node_id' => 'S2', 'source_id' => 'textfield', 'source' => []],
                  ['node_id' => 'S3', 'source_id' => 'textfield', 'source' => []],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
    $instance->setNewPresent($state, '');

    // Move S1 to position 1.
    $instance->moveToSlot('S1', 'A', 'slot_1', 1);
    $state = $instance->getCurrentState();
    $slot = $this->getComponentSlot($state[0], 'slot_1');
    self::assertSame('S2', $slot[0]['node_id']);
    self::assertSame('S1', $slot[1]['node_id']);
    self::assertSame('S3', $slot[2]['node_id']);
  }

  /**
   * Test the move from layout to component.
   */
  public function testMoveFromLayoutToComponent(): void {
    $instance = $this->createDisplayBuilderInstance();

    // Root has Layout L1 (with S1) and Component A.
    $state = [
      [
        'node_id' => 'L1',
        'source_id' => 'layout',
        'source' => [
          'plugin_id' => 'layout_onecol',
          'regions' => [
            'content' => [['node_id' => 'S1', 'source_id' => 'textfield', 'source' => []]],
          ],
        ],
      ],
      [
        'node_id' => 'A',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_2',
            'slots' => ['slot_1' => ['sources' => []]],
          ],
        ],
      ],
    ];
    $instance->setNewPresent($state, '');

    // Move S1 to A slot_1.
    $instance->moveToSlot('S1', 'A', 'slot_1', 0);
    $state = $instance->getCurrentState();

    self::assertEmpty($this->getLayoutRegion($state[0], 'content'));
    self::assertSame('S1', $this->getComponentSlot($state[1], 'slot_1')[0]['node_id']);
  }

  /**
   * Test the move to same region of same layout.
   */
  public function testMoveToSameRegionOfSameLayout(): void {
    $instance = $this->createDisplayBuilderInstance();

    // L1 has [S1, S2, S3].
    $state = [
      [
        'node_id' => 'L1',
        'source_id' => 'layout',
        'source' => [
          'plugin_id' => 'layout_onecol',
          'regions' => [
            'content' => [
              ['node_id' => 'S1', 'source_id' => 'textfield', 'source' => []],
              ['node_id' => 'S2', 'source_id' => 'textfield', 'source' => []],
              ['node_id' => 'S3', 'source_id' => 'textfield', 'source' => []],
            ],
          ],
        ],
      ],
    ];
    $instance->setNewPresent($state, '');

    $instance->moveToSlot('S1', 'L1', 'content', 1);
    $state = $instance->getCurrentState();
    $region = $this->getLayoutRegion($state[0], 'content');
    self::assertSame('S2', $region[0]['node_id']);
    self::assertSame('S1', $region[1]['node_id']);
    self::assertSame('S3', $region[2]['node_id']);
  }

  /**
   * Get component slot value.
   */
  private function getComponentSlot(array $component, string $slot_id): array {
    return \array_values($component['source']['component']['slots'][$slot_id]['sources'] ?? []);
  }

  /**
   * Get layout region value.
   */
  private function getLayoutRegion(array $component, string $slot_id): array {
    return \array_values($component['source']['regions'][$slot_id] ?? []);
  }

}
