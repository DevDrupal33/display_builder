<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Plugin\display_builder\Island\BuilderPanel;
use Drupal\display_builder\Plugin\display_builder\Island\LayersPanel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the dropzone renderables in panels.
 *
 * @internal
 */
#[CoversClass(BuilderPanel::class)]
#[CoversClass(LayersPanel::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class DropzoneTest extends DisplayBuilderKernelTestBase {

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
   * Test the ::build() method.
   */
  public function testBuild(): void {
    $node_id = \bin2hex(\random_bytes(8));
    $instance = Instance::create([
      'id' => 'test_instance',
      // Because there is no proper Drupal integration to rely on, we set the
      // instance ID and the profile entity themselves as plugin configuration.
      'buildable' => [
        'plugin_id' => 'test',
        'configuration' => [
          'instance_id' => 'test_instance',
          'profile_id' => 'default',
        ],
      ],
    ]);

    $builder_panel = $this->createIslandPlugin('builder');
    $this->testBuildInPanel($builder_panel, $instance, $node_id);
    $layers = $this->createIslandPlugin('layers');
    $this->testBuildInPanel($layers, $instance, $node_id);
  }

  /**
   * Test in a panel.
   */
  private function testBuildInPanel(IslandInterface $panel, InstanceInterface $instance, string $node_id): void {
    $data = [
      [
        'source_id' => 'component',
        'node_id' => $node_id,
        'source' => [
          'component' => ['component_id' => 'display_builder_test:test_1'],
        ],
      ],
    ];

    $root_dropzone = $panel->build($instance, $data, []);
    self::assertSame('display_builder:dropzone', $root_dropzone['#component']);
    self::assertSame('root', $root_dropzone['#props']['variant']);
    self::assertSame('test_instance', $root_dropzone['#attributes']['data-db-id']);
    $url = '/api/display-builder/test_instance?from=' . $panel->getPluginId();
    self::assertSame($url, $root_dropzone['#attributes']['hx-post']);

    $nested_dropzone = match ($panel->getPluginId()) {
      'layers' => $root_dropzone['#slots']['content'][0]['#slots']['children'][0][1],
      default => $root_dropzone['#slots']['content'][0]['content']['#slots']['slot_1'],
    };
    self::assertSame('display_builder:dropzone', $nested_dropzone['#component']);
    self::assertSame('highlighted', $nested_dropzone['#props']['variant']);
    self::assertSame('test_instance', $nested_dropzone['#attributes']['data-db-id']);
    self::assertSame('slot_1', $nested_dropzone['#attributes']['data-slot-id']);
    self::assertSame($node_id, $nested_dropzone['#attributes']['data-node-id']);
    // @see https://playwright.dev/docs/locators#locate-by-test-id
    self::assertSame($node_id . '_slot_1', $nested_dropzone['#attributes']['data-testid']);
    $url = '/api/display-builder/test_instance/_node/' . $node_id . '/slot_1?from=' . $panel->getPluginId();
    self::assertSame($url, $nested_dropzone['#attributes']['hx-post']);
  }

}
