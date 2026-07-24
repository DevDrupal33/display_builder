<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Island\IslandPluginManager;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IslandPluginManager discovery and grouping rules.
 *
 * The manager decides which islands exist at all and in what order they
 * render, so a mistake here removes UI rather than breaking it loudly. Its
 * rules run on real attribute discovery over real plugin classes - mocking the
 * discovery would only assert the mock - so this is a kernel test.
 *
 * Note this suite deliberately does *not* install ui_styles, which is what
 * makes the modules-constraint test meaningful.
 *
 * @internal
 */
#[CoversClass(IslandPluginManager::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class IslandPluginManagerTest extends DisplayBuilderKernelTestBase {

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
   * The manager under test.
   */
  private IslandPluginManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder']);

    $this->manager = $this->container->get('plugin.manager.db_island');
  }

  /**
   * Test islands requiring an absent module are dropped from discovery.
   *
   * StylesPanel and MenuStyles both declare modules: ['ui_styles'], which is
   * not installed here. They must not merely fail to render - they must be
   * absent from the definitions, or the profile form would offer an island
   * that cannot work.
   */
  public function testIslandsRequiringAnAbsentModuleAreDropped(): void {
    self::assertFalse($this->container->get('module_handler')->moduleExists('ui_styles'));

    $definitions = $this->manager->getDefinitions();

    self::assertArrayNotHasKey('styles', $definitions);
    self::assertArrayNotHasKey('menu_styles', $definitions);
    // Control: an island with no module constraint is still discovered, so the
    // assertions above cannot pass merely because discovery found nothing.
    self::assertArrayHasKey('test_minimal', $definitions);
  }

  /**
   * Test islands are grouped by their type.
   *
   * The view builder renders each group in its own region, so an island
   * arriving under the wrong key lands in the wrong part of the screen.
   */
  public function testIslandsAreGroupedByType(): void {
    $islands = $this->manager->getIslandsByTypes();

    self::assertArrayHasKey(IslandType::View->value, $islands);
    self::assertArrayHasKey('test_minimal', $islands[IslandType::View->value]);

    foreach ($islands as $type => $group) {
      foreach ($group as $id => $island) {
        self::assertSame(
          $type,
          $island->getPluginDefinition()['type']->value,
          \sprintf('Island %s is filed under its own type.', $id),
        );
      }
    }
  }

  /**
   * Test the filter keeps only the requested islands.
   */
  public function testFilterKeepsOnlyTheRequestedIslands(): void {
    $islands = $this->manager->getIslandsByTypes([], [], ['test_minimal' => 0]);

    $ids = [];

    foreach ($islands as $group) {
      $ids = \array_merge($ids, \array_keys($group));
    }

    self::assertSame(['test_minimal'], $ids);
  }

  /**
   * Test the filter's values order the result.
   *
   * The filter array is the profile's enabled-islands map, id => weight, and
   * it is the only thing deciding panel order. Asserting both orderings of the
   * same set is what makes this meaningful: a manager ignoring the weights
   * entirely would still satisfy either one alone.
   */
  public function testFilterWeightsOrderTheResult(): void {
    $ascending = $this->manager->getIslandsByTypes([], [], [
      'test_index_raw' => 0,
      'test_logs_raw' => 10,
    ]);
    $descending = $this->manager->getIslandsByTypes([], [], [
      'test_index_raw' => 10,
      'test_logs_raw' => 0,
    ]);

    $view = IslandType::View->value;
    self::assertSame(['test_index_raw', 'test_logs_raw'], \array_keys($ascending[$view]));
    self::assertSame(['test_logs_raw', 'test_index_raw'], \array_keys($descending[$view]));
  }

  /**
   * Test per-island configuration reaches the instance it belongs to.
   *
   * CreateInstances() keys configuration by plugin ID, so a slip there would
   * hand one island another's settings - silent, and hard to trace back from
   * the rendered panel.
   */
  public function testConfigurationIsRoutedToItsOwnIsland(): void {
    $islands = $this->manager->getIslandsByTypes([], [
      'test_minimal' => ['label' => 'Configured label'],
    ]);

    $configuration = $islands[IslandType::View->value]['test_minimal']->getConfiguration();

    self::assertSame('Configured label', $configuration['label']);
  }

  /**
   * Test contexts are passed to every island regardless of configuration.
   *
   * Contexts are merged into each island's configuration rather than keyed per
   * island, so an island with no configuration of its own still receives them.
   */
  public function testContextsReachIslandsWithoutConfiguration(): void {
    $contexts = ['display_builder' => 'a context'];

    $islands = $this->manager->getIslandsByTypes($contexts);

    $configuration = $islands[IslandType::View->value]['test_minimal']->getConfiguration();
    self::assertSame($contexts, $configuration['contexts']);
  }

}
