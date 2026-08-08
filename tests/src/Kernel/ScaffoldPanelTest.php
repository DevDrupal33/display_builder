<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Plugin\display_builder\Island\ScaffoldPanel;
use Drupal\display_builder\Plugin\display_builder\Island\ViewPanelBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ScaffoldPanel island build output.
 *
 * Pins the attribute contract the Scaffold panel exposes on its rows and
 * dropzones. With no components configured for real rendering, every row is a
 * schematic wireframe card. Both the drag-and-drop JavaScript and the
 * e2e tests select on these. Each component row carries a
 * data-testid="layer_<source_id>" so Playwright can address a specific
 * component. Asserting it here makes a silent change a red test instead of a
 * mystery.
 *
 * @internal
 */
#[CoversClass(ViewPanelBase::class)]
#[CoversClass(ScaffoldPanel::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ScaffoldPanelTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'ui_styles',
    // Provides the 'test' style plugin so StylesPanel::getSummary() resolves.
    'ui_styles_test',
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
   * Test the node and slot identity attributes of a component row.
   */
  public function testComponentRowAttributes(): void {
    $renderable = $this->buildScaffold();
    $row = $renderable['#slots']['content'][0];

    self::assertSame('layer_display_builder_test:test_1', $row['#attributes']['data-testid']);
    self::assertSame('component_1', $row['#attributes']['data-node-id']);
    // Used by the e2e tests to tell identical rows apart.
    self::assertArrayHasKey('data-node-title', $row['#attributes']);
  }

  /**
   * Test that a slot dropzone carries its own and its parent's identity.
   */
  public function testSlotDropzoneAttributes(): void {
    $renderable = $this->buildScaffold();
    $dropzone = $this->findSlotDropzone($renderable['#slots']['content'][0]);

    self::assertNotNull($dropzone, 'The component row exposes a slot dropzone.');
    self::assertSame('dropzone_slot_1', $dropzone['#attributes']['data-testid']);
    self::assertSame('slot_1', $dropzone['#attributes']['data-slot-id']);
    // The parent node ID, so a drop knows which component it lands in.
    self::assertSame('component_1', $dropzone['#attributes']['data-node-id']);
  }

  /**
   * Test that a block nested in a slot keeps its source type.
   */
  public function testNestedBlockKeepsSourceType(): void {
    $renderable = $this->buildScaffold();
    $dropzone = $this->findSlotDropzone($renderable['#slots']['content'][0]);
    $block = $dropzone['#slots']['content'][0];

    self::assertSame('textfield', $block['#attributes']['data-node-type']);
    self::assertSame('block_1', $block['#attributes']['data-node-id']);
  }

  /**
   * A component on the render allowlist is rendered for real, not schematic.
   *
   * The real-render path stamps the bare component ID as the row testid
   * (the schematic path prefixes it with "layer_"), so the two
   * are distinguishable by that attribute alone.
   */
  public function testConfiguredComponentRendersForReal(): void {
    $renderable = $this->buildScaffold(['components' => 'display_builder_test:test_1']);

    // The bare component-id testid is present (real render); the schematic
    // "layer_display_builder_test:test_1" is not.
    self::assertTrue(
      $this->hasTestid($renderable, 'display_builder_test:test_1'),
      'The allowlisted component is rendered for real.'
    );
    self::assertFalse(
      $this->hasTestid($renderable, 'layer_display_builder_test:test_1'),
      'It is not the schematic layer row.'
    );
  }

  /**
   * A schematic layer gains an info slot from component config and styles.
   *
   * Exercises ScaffoldPanel::addComponentSettingsSummary() (the prop
   * summary) and ::addThirdPartySettingsSummary() (the styles summary), both of
   * which early-return on a bare node and so were otherwise never populated.
   */
  public function testLayerInfoSummaries(): void {
    $instance = Instance::create(['id' => 'test_instance', 'label' => 'Test Instance']);
    $data = [
      [
        'node_id' => 'component_1',
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => 'display_builder_test:test_1',
            'props' => [
              'prop_string' => [
                'source_id' => 'textfield',
                'source' => ['value' => 'My Title'],
              ],
            ],
          ],
        ],
        'third_party_settings' => [
          'styles' => ['selected' => ['test' => 'test'], 'extra' => ''],
        ],
      ],
    ];

    $renderable = $this->createIslandPlugin('scaffold')->build($instance, $data, []);
    $info = $this->findLayerInfo($renderable);

    self::assertNotNull($info, 'The layer has an info slot.');
    $flat = \implode(' | ', $this->flattenValues($info));
    self::assertStringContainsString('My Title', $flat, 'The component config summary is present.');
    self::assertStringContainsString('Config', $flat);
    // The styles third-party-settings summary contributes its "Styles" heading.
    self::assertStringContainsString('Styles', $flat, 'The styles summary is present.');
  }

  /**
   * The configuration summary counts the components rendered for real.
   */
  public function testConfigurationSummary(): void {
    $plugin = $this->createIslandPlugin('scaffold', ['components' => "display_builder_test:test_1\ndisplay_builder_test:test_2"]);
    $summary = $plugin->configurationSummary();

    self::assertStringContainsString('2 components rendered with real output', (string) $summary[0]);
  }

  /**
   * The configuration form exposes the render-allowlist textarea.
   */
  public function testBuildConfigurationForm(): void {
    $plugin = $this->createIslandPlugin('scaffold');
    $form = $plugin->buildConfigurationForm([], new FormState());

    self::assertSame('textarea', $form['components']['#type']);
  }

  /**
   * Find the info slot of the first display_builder:layer in the tree.
   *
   * @param array $build
   *   A render array to walk.
   *
   * @return array|null
   *   The layer's info slot, or NULL.
   */
  private function findLayerInfo(array $build): ?array {
    if (($build['#component'] ?? NULL) === 'display_builder:layer' && isset($build['#slots']['info'])) {
      return $build['#slots']['info'];
    }

    foreach ($build as $child) {
      if (\is_array($child)) {
        $found = $this->findLayerInfo($child);

        if ($found !== NULL) {
          return $found;
        }
      }
    }

    return NULL;
  }

  /**
   * Collect every scalar '#value' / '#plain_text' string in a render array.
   *
   * @param array $build
   *   A render array to walk.
   *
   * @return string[]
   *   The collected strings, cast to string.
   */
  private function flattenValues(array $build): array {
    $out = [];

    foreach ($build as $key => $value) {
      if (($key === '#value' || $key === '#plain_text') && (\is_string($value) || $value instanceof \Stringable)) {
        $out[] = (string) $value;
      }
      elseif (\is_array($value)) {
        $out = \array_merge($out, $this->flattenValues($value));
      }
    }

    return $out;
  }

  /**
   * Whether any element in the tree carries the given data-testid.
   *
   * @param array $build
   *   A render array to walk.
   * @param string $testid
   *   The exact data-testid to find.
   *
   * @return bool
   *   TRUE if present anywhere in the tree.
   */
  private function hasTestid(array $build, string $testid): bool {
    if (($build['#attributes']['data-testid'] ?? NULL) === $testid) {
      return TRUE;
    }

    foreach ($build as $child) {
      if (\is_array($child) && $this->hasTestid($child, $testid)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Build the Scaffold panel over a component holding one block in a slot.
   *
   * @param array $configuration
   *   (Optional) Island plugin configuration, e.g. the render allowlist.
   *
   * @return array
   *   The Scaffold panel renderable.
   */
  private function buildScaffold(array $configuration = []): array {
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

    return $this->createIslandPlugin('scaffold', $configuration)->build($instance, $data, []);
  }

  /**
   * Find the first slot dropzone inside a component row.
   *
   * @param array $row
   *   The component row renderable.
   *
   * @return array|null
   *   The dropzone renderable, or NULL when the row exposes none.
   */
  private function findSlotDropzone(array $row): ?array {
    foreach ($row['#slots']['children'] ?? [] as $child) {
      foreach ((array) $child as $part) {
        if (isset($part['#attributes']['data-slot-id'])) {
          return $part;
        }
      }
    }

    return NULL;
  }

}
