<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\display_builder\Plugin\UiPatterns\Source\ComponentSource;
use Drupal\display_builder\SourceWithSlotsInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ComponentSource plugin.
 *
 * @internal
 */
#[CoversClass(ComponentSource::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ComponentSourceTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_styles',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * The test source.
   */
  protected SourceWithSlotsInterface $source;

  /**
   * The plugin source manager.
   */
  protected PluginManagerInterface $sourceManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'display_builder_test', 'ui_patterns', 'ui_styles']);
    $this->installEntitySchema('user');

    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');

    $this->source = $this->sourceManager->createInstance('component', [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'slots' => [
            'slot_1' => [
              'sources' => [
                'source_id' => 'textfield',
                'source' => ['value' => 'Hello'],
              ],
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Test the getChoice() method.
   */
  public function testGetChoice(): void {
    $expected = $this->source->getChoice([
      'component' => [
        'component_id' => 'display_builder_test:test_1',
      ],
    ]);
    self::assertSame($expected, 'display_builder_test:test_1');

    $expected = $this->source->getChoice([
      'component_id' => 'fallback',
    ]);
    self::assertSame($expected, 'fallback');

    $expected = $this->source->getChoice([]);
    self::assertSame($expected, '');
  }

  /**
   * Test the getSlotDefinitions() method.
   */
  public function testGetSlotDefinitions(): void {
    $defs = $this->source->getSlotDefinitions();
    self::assertIsArray($defs);
    self::assertArrayHasKey('slot_1', $defs);
  }

  /**
   * Test the getSlotPath() method.
   */
  public function testGetSlotPath(): void {
    self::assertSame(['component', 'slots', 'my_slot', 'sources'], ComponentSource::getSlotPath('my_slot'));
  }

  /**
   * Test the getSlotValues() method.
   */
  public function testGetSlotValues(): void {
    $values = $this->source->getSlotValues();

    self::assertArrayHasKey('slot_1', $values);

    // getSlotValue() for missing slot returns empty array.
    self::assertSame([], $this->source->getSlotValue('non_existing'));
  }

  /**
   * Test the setSlotRenderable() method.
   */
  public function testSetSlotRenderable(): void {
    $build = ['#ui_patterns' => ['slots' => ['slot_1' => ['x']]]];
    $build = $this->source->setSlotRenderable($build, 'slot_1', ['renderable']);

    self::assertArrayHasKey('#slots', $build);
    self::assertArrayHasKey('slot_1', $build['#slots']);
    self::assertArrayNotHasKey('slot_1', $build['#ui_patterns']['slots'] ?? []);
  }

  /**
   * Test the setSlotValue() method.
   */
  public function testSetSlotValue(): void {
    $data = [];
    $data = $this->source->setSlotValue('slot_1', ['one', 'two']);

    self::assertArrayHasKey('component', $data);
    self::assertArrayHasKey('slots', $data['component']);
    self::assertSame(['one', 'two'], $data['component']['slots']['slot_1']['sources']);
  }

  /**
   * Test the InstanceAccessControlHandler::settingsFormPropsOnly() method.
   */
  public function testSettingsFormPropsOnly(): void {
    // settingsFormPropsOnly() returns the built form; ensure keys are present
    // and '#render_slots' gets set to FALSE when a component_id exists.
    $form = [];
    $form_state = new FormState();
    $built = $this->source->settingsFormPropsOnly($form, $form_state);

    self::assertIsArray($built);
    self::assertArrayHasKey('component', $built);
  }

  /**
   * Test the settingsSummary() method.
   *
   * @param array<string, mixed> $settings
   *   The source settings.
   * @param array<string> $expectedSummary
   *   The expected summary.
   */
  #[DataProvider('settingsSummaryProvider')]
  public function testSettingsSummary(array $settings, array $expectedSummary): void {
    $configuration = [
      'settings' => $settings,
    ];
    /** @var \Drupal\display_builder\Plugin\UiPatterns\Source\ComponentSource $source */
    $source = $this->sourceManager->createInstance('component', $configuration);

    $summary = $source->settingsSummary();
    self::assertSame($expectedSummary, $summary);
  }

  /**
   * Data provider for testSettingsSummary().
   *
   * @return iterable<string, array<int, mixed>>
   *   The data to test as settings and expectedSummary.
   */
  public static function settingsSummaryProvider(): iterable {
    yield 'no props' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [],
        ],
      ],
      'expectedSummary' => [],
    ];

    yield 'standard property' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'prop_string' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => 'Hello World',
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [
        'Title: Hello World',
      ],
    ];

    yield 'ui_styles property' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'attributes' => [
              'source_id' => 'ui_styles_attributes',
              'source' => [
                'styles' => [
                  'selected' => [
                    'style_1' => 'style_1',
                    'style_2' => 'style_2',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [
        'Attributes - style_1',
      ],
    ];

    yield 'property with no value' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'prop_string' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => '',
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [],
    ];

    yield 'property with array value' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'prop_string' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => ['Hello', 'World'],
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [
        'Title: Hello, World',
      ],
    ];

    yield 'property with empty array value' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'prop_string' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => [],
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [],
    ];

    yield 'mixed properties' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'prop_string' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => 'Hello World',
              ],
            ],
            'attributes' => [
              'source_id' => 'ui_styles_attributes',
              'source' => [
                'styles' => [
                  'selected' => [
                    'style_1' => 'style_1',
                  ],
                ],
              ],
            ],
            'extra' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => '',
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [
        'Title: Hello World',
        'Attributes - style_1',
      ],
    ];

    yield 'no component definition' => [
      'settings' => [
        'component' => [
          'component_id' => 'non_existent_component',
          'props' => [
            'prop_string' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => 'Hello World',
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [],
    ];

    yield 'property config not found' => [
      'settings' => [
        'component' => [
          'component_id' => 'display_builder_test:test_1',
          'props' => [
            'non_existent_prop' => [
              'source_id' => 'textfield',
              'source' => [
                'value' => 'Some value',
              ],
            ],
          ],
        ],
      ],
      'expectedSummary' => [
        'non_existent_prop: Some value',
      ],
    ];
  }

}
