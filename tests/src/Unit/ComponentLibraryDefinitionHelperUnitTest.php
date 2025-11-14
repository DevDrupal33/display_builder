<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Plugin\Component as ComponentPlugin;
use Drupal\ui_patterns\ComponentPluginManager as UiPatternsComponentPluginManager;
use Drupal\display_builder\ComponentLibraryDefinitionHelper;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test the ComponentLibraryDefinitionHelper class.
 *
 * @internal
 */
#[CoversClass(ComponentLibraryDefinitionHelper::class)]
#[Group('display_builder')]
final class ComponentLibraryDefinitionHelperUnitTest extends UnitTestCase {

  /**
   * The SDC manager mock.
   */
  private MockObject $sdcManager;

  /**
   * The source manager mock.
   */
  private MockObject $sourceManager;

  /**
   * The service under test.
   */
  private ComponentLibraryDefinitionHelper $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sdcManager = $this->createMock(UiPatternsComponentPluginManager::class);
    $this->sourceManager = $this->createMock(SourcePluginManager::class);

    $this->helper = new ComponentLibraryDefinitionHelper($this->sdcManager, $this->sourceManager);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Test the getDefinitions method with a simple configuration.
   */
  public function testSimpleGetDefinitions(): void {
    $definitions = [
      'test:one' => ['id' => 'test:one', 'machineName' => 'one', 'name' => 'One', 'provider' => 'test', 'category' => 'Test Category', 'template' => 'component.twig'],
      'test:two' => ['id' => 'test:two', 'machineName' => 'two', 'name' => 'Two', 'provider' => 'test', 'category' => 'Test Category', 'status' => 'experimental', 'template' => 'component.twig'],
      'other:three' => ['id' => 'other:three', 'machineName' => 'three', 'name' => 'Three', 'provider' => 'other', 'category' => 'Other Category', 'template' => 'component.twig'],
      'test:four' => ['id' => 'test:four', 'machineName' => 'four', 'name' => 'Four', 'provider' => 'test', 'category' => 'Test Category', 'noUi' => TRUE, 'template' => 'component.twig'],
      'test:five' => ['id' => 'test:five', 'machineName' => 'five', 'name' => 'Five', 'provider' => 'test', 'category' => 'Test Category', 'status' => 'deprecated', 'template' => 'component.twig'],
      'test:six' => ['id' => 'test:six', 'machineName' => 'six', 'name' => '(Six)', 'provider' => 'test', 'category' => 'Test Category', 'template' => 'component.twig'],
    ];

    $this->sdcManager->method('getNegotiatedSortedDefinitions')->willReturn($definitions);

    $sourceMock = $this->createMock(SourceWithChoicesInterface::class);
    $sourceMock->method('getChoiceSettings')->willReturn(['component' => ['props' => []]]);
    $this->sourceManager->method('createInstance')->with('component')->willReturn($sourceMock);

    $componentMap = [
      ['test:one', $this->createComponent('test:one', $definitions['test:one'])],
      ['test:two', $this->createComponent('test:two', $definitions['test:two'])],
      ['test:six', $this->createComponent('test:six', $definitions['test:six'])],
    ];
    $this->sdcManager->method('find')->willReturnMap($componentMap);

    $configuration = [
      'exclude' => ['other'],
      'component_status' => ['experimental'],
      'include_no_ui' => FALSE,
    ];

    $result = $this->helper->getDefinitions($configuration);

    self::assertCount(3, $result['filtered']);
    self::assertEquals([
      'test:six' => $definitions['test:six'],
      'test:one' => $definitions['test:one'],
      'test:two' => $definitions['test:two'],
    ], $result['filtered']);

    self::assertCount(1, $result['grouped']);
    self::assertCount(3, $result['grouped']['Test Category']);

    self::assertCount(3, $result['sources']);
    self::assertArrayHasKey('test:one', $result['sources']);
    self::assertArrayHasKey('test:two', $result['sources']);
    self::assertArrayHasKey('test:six', $result['sources']);
  }

  /**
   * Test excluding components by ID.
   */
  public function testExcludeById(): void {
    $definitions = [
      'test:one' => ['id' => 'test:one', 'machineName' => 'one', 'name' => 'One', 'provider' => 'test', 'category' => 'Test Category', 'template' => 'component.twig'],
      'test:two' => ['id' => 'test:two', 'machineName' => 'two', 'name' => 'Two', 'provider' => 'test', 'category' => 'Test Category', 'template' => 'component.twig'],
    ];

    $this->sdcManager->method('getNegotiatedSortedDefinitions')->willReturn($definitions);

    $sourceMock = $this->createMock(SourceWithChoicesInterface::class);
    $sourceMock->method('getChoiceSettings')->willReturn(['component' => ['props' => []]]);
    $this->sourceManager->method('createInstance')->with('component')->willReturn($sourceMock);

    $this->sdcManager->method('find')->with('test:two')->willReturn($this->createComponent('test:two', $definitions['test:two']));

    $configuration = [
      'exclude_id' => 'test:one',
      'exclude' => [],
      'component_status' => [],
      'include_no_ui' => FALSE,
    ];

    $result = $this->helper->getDefinitions($configuration);

    self::assertCount(1, $result['filtered']);
    self::assertArrayHasKey('test:two', $result['filtered']);
  }

  /**
   * Test including noUi components.
   */
  public function testIncludeNoUi(): void {
    $definitions = [
      'test:one' => ['id' => 'test:one', 'machineName' => 'one', 'name' => 'One', 'provider' => 'test', 'category' => 'Test Category', 'noUi' => TRUE, 'template' => 'component.twig'],
      'test:two' => ['id' => 'test:two', 'machineName' => 'two', 'name' => 'Two', 'provider' => 'test', 'category' => 'Test Category', 'template' => 'component.twig'],
    ];

    $this->sdcManager->method('getNegotiatedSortedDefinitions')->willReturn($definitions);

    $sourceMock = $this->createMock(SourceWithChoicesInterface::class);
    $sourceMock->method('getChoiceSettings')->willReturn(['component' => ['props' => []]]);
    $this->sourceManager->method('createInstance')->with('component')->willReturn($sourceMock);

    $componentMap = [
      ['test:one', $this->createComponent('test:one', $definitions['test:one'])],
      ['test:two', $this->createComponent('test:two', $definitions['test:two'])],
    ];
    $this->sdcManager->method('find')->willReturnMap($componentMap);

    $configuration = [
      'exclude' => [],
      'component_status' => [],
      'include_no_ui' => TRUE,
    ];

    $result = $this->helper->getDefinitions($configuration);

    self::assertCount(2, $result['filtered']);
  }

  /**
   * Test filtering by status.
   */
  public function testFilterByStatus(): void {
    $definitions = [
      'test:one' => ['id' => 'test:one', 'machineName' => 'one', 'name' => 'One', 'provider' => 'test', 'category' => 'Test Category', 'status' => 'deprecated', 'template' => 'component.twig'],
      'test:two' => ['id' => 'test:two', 'machineName' => 'two', 'name' => 'Two', 'provider' => 'test', 'category' => 'Test Category', 'status' => 'experimental', 'template' => 'component.twig'],
      'test:three' => ['id' => 'test:three', 'machineName' => 'three', 'name' => 'Three', 'provider' => 'test', 'category' => 'Test Category', 'status' => 'stable', 'template' => 'component.twig'],
      'test:four' => ['id' => 'test:four', 'machineName' => 'four', 'name' => 'Four', 'provider' => 'test', 'category' => 'Test Category', 'template' => 'component.twig'],
    ];

    $this->sdcManager->method('getNegotiatedSortedDefinitions')->willReturn($definitions);

    $sourceMock = $this->createMock(SourceWithChoicesInterface::class);
    $sourceMock->method('getChoiceSettings')->willReturn(['component' => ['props' => []]]);
    $this->sourceManager->method('createInstance')->with('component')->willReturn($sourceMock);

    $componentMap = [
      ['test:two', $this->createComponent('test:two', $definitions['test:two'])],
      ['test:three', $this->createComponent('test:three', $definitions['test:three'])],
      ['test:four', $this->createComponent('test:four', $definitions['test:four'])],
    ];
    $this->sdcManager->method('find')->willReturnMap($componentMap);

    $configuration = [
      'exclude' => [],
      'component_status' => ['experimental'],
      'include_no_ui' => FALSE,
    ];

    $result = $this->helper->getDefinitions($configuration);

    self::assertCount(3, $result['filtered']);
    self::assertArrayHasKey('test:two', $result['filtered']);
    self::assertArrayHasKey('test:three', $result['filtered']);
    self::assertArrayHasKey('test:four', $result['filtered']);
  }

  /**
   * Test that a component with a required prop without a default is skipped.
   */
  public function testRequiredPropWithoutDefault(): void {
    $definitions = [
      'test:one' => [
        'id' => 'test:one',
        'machineName' => 'one',
        'name' => 'One',
        'provider' => 'test',
        'category' => 'Test Category',
        'template' => 'component.twig',
        'props' => [
          'required' => ['title'],
          'properties' => [
            'title' => [
              'type' => 'string',
              'ui_patterns' => (object) ['type_definition' => NULL],
            ],
          ],
        ],
      ],
    ];

    $this->sdcManager->method('getNegotiatedSortedDefinitions')->willReturn($definitions);

    $sourceMock = $this->createMock(SourceWithChoicesInterface::class);
    $sourceMock->method('getChoiceSettings')->willReturn(['component' => ['props' => []]]);
    $this->sourceManager->method('createInstance')->with('component')->willReturn($sourceMock);

    $this->sdcManager->method('find')->with('test:one')->willReturn($this->createComponent('test:one', $definitions['test:one']));

    $configuration = [
      'exclude' => [],
      'component_status' => [],
      'include_no_ui' => FALSE,
    ];

    $result = $this->helper->getDefinitions($configuration);

    self::assertCount(0, $result['filtered']);
  }

  /**
   * Helper to create a mock component.
   */
  private function createComponent(string $id, array $definition): ComponentPlugin {
    $definition['path'] = 'tests/fixtures/' . $id;

    // The third argument to ComponentPlugin is the plugin definition.
    return new ComponentPlugin(['app_root' => '.'], $id, $definition);
  }

}
