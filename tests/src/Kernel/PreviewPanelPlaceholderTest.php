<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Plugin\display_builder\Island\PreviewPanel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for what the Preview shows where a block shows nothing.
 *
 * Preview is what the visitor gets, so the two kinds of nothing part ways
 * here. A block the page will fill still shows its placeholder, because the
 * page really will fill it. A block that simply rendered nothing is dropped:
 * standing something in its place would promise a visitor markup that is
 * never coming.
 *
 * @internal
 */
#[CoversClass(PreviewPanel::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class PreviewPanelPlaceholderTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'layout_discovery',
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
   * A block the page will fill keeps its placeholder in the Preview.
   *
   * These render a wrapper with nothing in it, which is markup as far as the
   * renderer is concerned, so no emptiness check would ever catch them. The
   * source replaces them, and the Preview leaves that replacement alone.
   *
   * @param string $plugin_id
   *   The block plugin to preview.
   * @param string $label
   *   The name expected in the placeholder's title slot.
   */
  #[DataProvider('providerPageFilledBlocks')]
  public function testPageFilledBlockIsNamed(string $plugin_id, string $label): void {
    $renderable = $this->preview($plugin_id);

    self::assertSame('component', $renderable['#type']);
    self::assertSame('display_builder:placeholder', $renderable['#component']);
    self::assertSame('region', $renderable['#props']['variant']);
    self::assertSame($label, (string) $renderable['#slots']['content']['title']['#value']);
    self::assertStringContainsString(
      'replaced by the page value',
      (string) $renderable['#slots']['content']['help']['#value']
    );
  }

  /**
   * Cases for ::testPageFilledBlockIsNamed().
   *
   * @return array
   *   Test cases, keyed by which piece of page chrome the block is.
   */
  public static function providerPageFilledBlocks(): array {
    return [
      'page chrome: tabs' => ['local_tasks_block', 'Tabs'],
      'page chrome: messages' => ['system_messages_block', 'Messages'],
      'page chrome: breadcrumb' => ['system_breadcrumb_block', 'Breadcrumbs'],
    ];
  }

  /**
   * A block that simply rendered nothing is dropped, not stood in for.
   *
   * Nothing is going to fill it later, so the visitor sees what the Canvas
   * placeholder was warning about: an empty area.
   */
  public function testEmptyBlockIsDropped(): void {
    $instance = Instance::create([
      'id' => 'test_instance_dropped',
      'label' => 'Test Instance',
    ]);

    $data = [
      [
        'source_id' => 'block',
        'node_id' => \bin2hex(\random_bytes(8)),
        'source' => ['plugin_id' => 'display_builder_test_empty'],
      ],
    ];

    self::assertSame([], $this->createIslandPlugin('preview')->build($instance, $data, ['in_iframe' => TRUE]));
  }

  /**
   * A page-filled block nested in a slot is named too.
   *
   * The regression this pins: the panel can only judge what it renders
   * itself, which in the Preview is the root nodes. A real page layout puts
   * its blocks inside a grid, several levels down, where they used to render
   * as nothing at all - no placeholder, no node, no way to see a style
   * applied to one.
   */
  public function testNestedChromeBlockIsNamed(): void {
    $instance = Instance::create([
      'id' => 'test_instance_nested',
      'label' => 'Test Instance',
    ]);

    $data = [
      [
        'node_id' => \bin2hex(\random_bytes(8)),
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_1',
            'slots' => [
              'slot_1' => [
                'sources' => [
                  [
                    'node_id' => \bin2hex(\random_bytes(8)),
                    'source_id' => 'block',
                    'source' => ['plugin_id' => 'system_messages_block'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $build = $this->createIslandPlugin('preview')->build($instance, $data, ['in_iframe' => TRUE]);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);

    self::assertStringContainsString('db-placeholder-region', $html);
    self::assertStringContainsString('Messages', $html);
  }

  /**
   * A page-filled block inside a layout region is named too.
   *
   * The layout source renders its regions itself, with another pass of the
   * element builder, so it has to hand the preview flag down the same way a
   * component does. Without that, everything inside a layout is told it is on
   * a real page and the block resolves to the builder's own chrome.
   */
  public function testBlockInLayoutRegionIsNamed(): void {
    $instance = Instance::create([
      'id' => 'test_instance_layout',
      'label' => 'Test Instance',
    ]);

    $data = [
      [
        'node_id' => \bin2hex(\random_bytes(8)),
        'source_id' => 'layout',
        'source' => [
          'layout_id' => 'layout_onecol',
          'regions' => [
            'content' => [
              [
                'node_id' => \bin2hex(\random_bytes(8)),
                'source_id' => 'block',
                'source' => ['plugin_id' => 'system_messages_block'],
              ],
            ],
          ],
        ],
      ],
    ];

    $build = $this->createIslandPlugin('preview')->build($instance, $data, ['in_iframe' => TRUE]);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);

    self::assertStringContainsString('db-placeholder-region', $html);
    self::assertStringContainsString('Messages', $html);
  }

  /**
   * A block that renders something is left alone.
   */
  public function testRenderingBlockIsLeftAlone(): void {
    self::assertArrayNotHasKey('#component', $this->preview('display_builder_test_correct'));
  }

  /**
   * Previews one block, the way the live-preview iframe renders it.
   *
   * @param string $plugin_id
   *   The block plugin to preview.
   *
   * @return array
   *   What Preview drew for it.
   */
  private function preview(string $plugin_id): array {
    $instance = Instance::create([
      'id' => 'test_instance_preview',
      'label' => 'Test Instance',
    ]);

    $data = [
      [
        'source_id' => 'block',
        'node_id' => \bin2hex(\random_bytes(8)),
        'source' => ['plugin_id' => $plugin_id],
      ],
    ];

    return $this->createIslandPlugin('preview')->build($instance, $data, ['in_iframe' => TRUE])[0];
  }

}
