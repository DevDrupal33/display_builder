<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the IslandPluginBase abstract class.
 *
 * @internal
 */
#[CoversClass(IslandPluginBase::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class IslandPluginBaseTest extends DisplayBuilderKernelTestBase {

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder']);
  }

  /**
   * Tests the ::label() method.
   */
  public function testLabel(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertSame('[Test] Minimal', $plugin->label());
  }

  /**
   * Tests the ::getTypeId() method.
   */
  public function testGetTypeId(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertSame('view', $plugin->getTypeId());
  }

  /**
   * Tests the ::getHtmlId() method.
   */
  public function testGetHtmlId(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertSame('island-my_builder-test_minimal', $plugin->getHtmlId('my_builder'));
  }

  /**
   * Tests the ::getIcon() method when an icon is defined.
   */
  public function testGetIconReturnsDefinedIcon(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertSame('test-icon', $plugin->getIcon());
  }

  /**
   * Tests the ::getIcon() method returns NULL when no icon is defined.
   */
  public function testGetIconReturnsNullWhenNotDefined(): void {
    $plugin = $this->createIsland('test_index_raw');
    self::assertNull($plugin->getIcon());
  }

  /**
   * Tests the ::keyboardShortcuts() method.
   */
  public function testKeyboardShortcutsReturnsEmptyArray(): void {
    self::assertSame([], IslandPluginBase::keyboardShortcuts());
  }

  /**
   * Tests that ::build() returns empty when no node_id is in data.
   */
  public function testBuildReturnsEmptyArrayWhenNodeIdMissing(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();

    self::assertSame([], $plugin->build($instance, ['some_key' => 'some_value'], []));
  }

  /**
   * Tests ::isApplicable().
   *
   * Even with node_id present, empty plugin-scoped third_party_settings makes
   * isApplicable() return FALSE.
   */
  public function testBuildReturnsEmptyArrayWhenPluginDataIsEmpty(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();

    $data = [
      'node_id' => 'some_node',
      'third_party_settings' => ['test_minimal' => []],
    ];

    self::assertSame([], $plugin->build($instance, $data, []));
  }

  /**
   * Tests that ::build() delegates to buildContent() when applicable.
   */
  public function testBuildReturnsBuildContentWhenApplicable(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();

    $result = $plugin->build($instance, ['node_id' => 'some_node', 'extra' => 'value'], []);
    self::assertSame(['#markup' => 'minimal content'], $result);
  }

  /**
   * Tests that ::build() uses plugin-scoped data from third_party_settings.
   */
  public function testBuildUsesThirdPartySettingsWhenPresent(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();

    $data = [
      'node_id' => 'some_node',
      'third_party_settings' => ['test_minimal' => ['scoped_key' => 'scoped_value']],
    ];

    self::assertSame(['#markup' => 'minimal content'], $plugin->build($instance, $data, []));
  }

  /**
   * Tests the ::isApplicable() method before build() is called.
   */
  public function testIsApplicableReturnsFalseBeforeBuild(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertFalse($plugin->isApplicable());
  }

  /**
   * Tests the ::isApplicable() method after build() sets a node_id.
   */
  public function testIsApplicableReturnsTrueAfterBuildWithNodeId(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();

    $plugin->build($instance, ['node_id' => 'some_node', 'extra' => 'value'], []);
    self::assertTrue($plugin->isApplicable());
  }

  /**
   * Tests the ::defaultConfiguration() method.
   */
  public function testDefaultConfigurationReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertSame([], $plugin->defaultConfiguration());
  }

  /**
   * Tests the ::getConfiguration() method.
   */
  public function testGetConfigurationReturnsMergedConfiguration(): void {
    $plugin = $this->createIsland('test_minimal', ['foo' => 'bar']);
    self::assertSame(['foo' => 'bar'], $plugin->getConfiguration());
  }

  /**
   * Tests the ::setConfiguration() method.
   */
  public function testSetConfigurationMergesWithDefaults(): void {
    $plugin = $this->createIsland('test_minimal');
    $plugin->setConfiguration(['baz' => 'qux']);
    self::assertSame(['baz' => 'qux'], $plugin->getConfiguration());
  }

  /**
   * Tests the ::configurationSummary() method.
   */
  public function testConfigurationSummaryReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    self::assertSame([], $plugin->configurationSummary());
  }

  /**
   * Tests the ::alterRenderable() method.
   */
  public function testAlterRenderableReturnsArrayUnchanged(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();

    $build = ['#type' => 'html_tag', '#tag' => 'div'];
    self::assertSame($build, $plugin->alterRenderable($instance, $build));
  }

  /**
   * Tests that ::onAttachToRoot() returns empty by default.
   */
  public function testOnAttachToRootDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onAttachToRoot($instance, 'node_1'));
  }

  /**
   * Tests that ::onAttachToSlot() returns empty by default.
   */
  public function testOnAttachToSlotDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onAttachToSlot($instance, 'node_1', 'parent_1'));
  }

  /**
   * Tests that ::onMove() returns empty by default.
   */
  public function testOnMoveDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onMove($instance, 'node_1'));
  }

  /**
   * Tests that ::onActive() returns empty by default.
   */
  public function testOnActiveDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onActive($instance, []));
  }

  /**
   * Tests that ::onUpdate() returns empty by default.
   */
  public function testOnUpdateDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onUpdate($instance, 'node_1'));
  }

  /**
   * Tests that ::onDelete() returns empty by default.
   */
  public function testOnDeleteDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onDelete($instance, 'parent_1'));
  }

  /**
   * Tests that ::onHistoryChange() returns empty by default.
   */
  public function testOnHistoryChangeDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onHistoryChange($instance));
  }

  /**
   * Tests that ::onPublish() returns empty by default.
   */
  public function testOnPublishDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onPublish($instance));
  }

  /**
   * Tests that ::onPresetSave() returns empty by default.
   */
  public function testOnPresetSaveDefaultReturnsEmptyArray(): void {
    $plugin = $this->createIsland('test_minimal');
    $instance = $this->createInstance();
    self::assertSame([], $plugin->onPresetSave($instance));
  }

  /**
   * Tests ::onAttachToRoot().
   *
   * Verified via TestIndexRawPanel::onAttachToRoot() which call
   * reloadWithGlobalData().
   */
  public function testReloadWithGlobalDataReturnsOutOfBandRenderable(): void {
    /** @var \Drupal\display_builder\Island\IslandInterface $plugin */
    $plugin = $this->createIsland('test_index_raw');
    $instance = $this->createInstance('my_builder');

    $result = $plugin->onAttachToRoot($instance, 'node_1');

    self::assertSame('html_tag', $result['#type']);
    self::assertSame('div', $result['#tag']);
    self::assertSame('innerHTML:#island-my_builder-test_index_raw', $result['#attributes']['hx-swap-oob']);
    self::assertArrayHasKey('content', $result);
  }

  /**
   * Creates an island plugin instance via the plugin manager.
   *
   * @param string $id
   *   The plugin ID.
   * @param array $configuration
   *   Optional plugin configuration.
   *
   * @return \Drupal\display_builder\Island\IslandInterface
   *   The island plugin instance.
   */
  private function createIsland(string $id, array $configuration = []): IslandInterface {
    return $this->createIslandPlugin($id, $configuration);
  }

  /**
   * Creates a bare Instance entity (unsaved).
   *
   * @param string $id
   *   The instance entity ID.
   *
   * @return \Drupal\display_builder\Entity\Instance
   *   The instance entity.
   */
  private function createInstance(string $id = 'test_instance'): Instance {
    return Instance::create([
      'id' => $id,
      'label' => 'Test Instance',
    ]);
  }

}
