<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Render\RendererInterface;
use Drupal\display_builder\Plugin\UiPatterns\Source\LayoutSource;
use Drupal\ui_patterns\SourceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the ::LayoutSource plugin.
 *
 * @internal
 */
#[CoversClass(LayoutSource::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class LayoutSourceTest extends DisplayBuilderKernelTestBase {

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
   * The test component configuration base.
   */
  protected array $configuration;

  /**
   * The test source.
   */
  protected SourceInterface $source;

  /**
   * The source plugin manager.
   */
  protected PluginManagerInterface $sourceManager;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * List of layouts available.
   */
  protected array $layoutDefinitions;

  /**
   * A layout to test against.
   */
  protected LayoutDefinition $layoutDefinition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'display_builder_test', 'ui_patterns']);
    $this->installEntitySchema('user');

    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');
    $this->renderer = $this->container->get('renderer');
    $this->source = $this->sourceManager->createInstance('layout');

    $this->layoutDefinitions = $this->container->get('plugin.manager.core.layout')->getDefinitions();
    $this->layoutDefinition = \reset($this->layoutDefinitions);
  }

  /**
   * Test the ::getChoice() method.
   */
  public function testGetChoice(): void {
    self::assertSame('my_layout', $this->source->getChoice(['layout_id' => 'my_layout']));
    self::assertSame('', $this->source->getChoice([]));
  }

  /**
   * Test the ::getChoices() method.
   */
  public function testGetChoices(): void {
    $choices = $this->source->getChoices();

    self::assertIsArray($choices);
    self::assertSame(\count($this->layoutDefinitions), \count($choices));

    self::assertArrayHasKey($this->layoutDefinition->id(), $choices);
    self::assertSame((string) $this->layoutDefinition->getLabel(), (string) $choices[$this->layoutDefinition->id()]['label']);
  }

  /**
   * Test the ::getSlotDefinitions() method.
   */
  public function testGetSlotDefinitions(): void {
    $settings = ['layout_id' => $this->layoutDefinition->id()];
    /** @var \Drupal\display_builder\Plugin\UiPatterns\Source\LayoutSource $source */
    $source = $this->sourceManager->createInstance('layout', ['settings' => $settings]);

    $defs = $source->getSlotDefinitions();
    self::assertIsArray($defs);

    $regions = $this->layoutDefinition->getRegions();

    $regionTestKey = \key($regions);
    $regionTest = \reset($regions);

    self::assertArrayHasKey($regionTestKey, $defs);
    self::assertSame((string) $regionTest['label'], (string) $defs[$regionTestKey]['title']);
  }

  /**
   * Test the ::getSlotPath() method.
   */
  public function testGetSlotPath(): void {
    self::assertSame(['regions', 'my_slot'], LayoutSource::getSlotPath('my_slot'));
  }

  /**
   * Test the ::getSlotValues() method.
   */
  public function testGetSlotValues(): void {
    $settings = ['layout_id' => $this->layoutDefinition->id(), 'regions' => ['top' => ['foo']]];
    /** @var \Drupal\display_builder\Plugin\UiPatterns\Source\LayoutSource $source */
    $source = $this->sourceManager->createInstance('layout', ['settings' => $settings]);

    $values = $source->getSlotValues();
    self::assertArrayHasKey('top', $values);
    self::assertSame(['foo'], $values['top']);
    self::assertSame(['foo'], $source->getSlotValue('top'));
    self::assertSame([], $source->getSlotValue('bottom'));
  }

  /**
   * Test the ::settingsFormPropsOnly() method.
   */
  public function testSettingsFormPropsOnly(): void {
    $settings = ['layout_id' => $this->layoutDefinition->id()];
    /** @var \Drupal\display_builder\Plugin\UiPatterns\Source\LayoutSource $source */
    $source = $this->sourceManager->createInstance('layout', ['settings' => $settings]);

    $form = [];
    $form_state = new FormState();
    $built = $source->settingsFormPropsOnly($form, $form_state);

    self::assertIsArray($built);
    self::assertArrayNotHasKey('regions', $built);
    self::assertArrayHasKey('layout_id', $built);
    self::assertSame('hidden', $built['layout_id']['#type']);
    self::assertSame($this->layoutDefinition->id(), $built['layout_id']['#value']);
  }

  /**
   * Test the ::setSlotValue() method.
   */
  public function testSetSlotValue(): void {
    $slot_source = [
      'source_id' => 'textfield',
      'source' => ['value' => 'Hello'],
    ];

    $settings = ['layout_id' => $this->layoutDefinition->id()];
    $source = $this->sourceManager->createInstance('layout', ['settings' => $settings]);
    $source->setSlotValue('content', [$slot_source]);
    $value = $source->getPropValue();
    $markup = $this->renderer->renderInIsolation($value);

    self::assertStringContainsString('Hello', (string) $markup);
  }

  /**
   * Test the ::calculateDependencies() method.
   */
  public function testCalculateDependencies(): void {
    $slot_source = [
      'source_id' => 'textfield',
      'source' => ['value' => 'Hello'],
    ];

    $settings = ['layout_id' => $this->layoutDefinition->id()];
    $source = $this->sourceManager->createInstance('layout', ['settings' => $settings]);
    $source->setSlotValue('content', [$slot_source]);

    $dependencies = $source->calculateDependencies();
    $expected = [
      'module' => [
        'ui_patterns',
        'layout_discovery',
      ],
    ];
    self::assertSame($expected, $dependencies);
  }

}
