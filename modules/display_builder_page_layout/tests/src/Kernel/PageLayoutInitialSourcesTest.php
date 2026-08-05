<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout as PageLayoutBuildable;
use Drupal\display_builder_page_layout\StartingPointType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the page layout starting points.
 *
 * @internal
 */
#[CoversClass(PageLayoutBuildable::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class PageLayoutInitialSourcesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
    'user',
    'display_builder',
    'display_builder_test',
    'display_builder_page_layout',
    'ui_patterns',
    'ui_patterns_field',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')->install(['display_builder_theme_test']);
    $this->config('system.theme')->set('default', 'display_builder_theme_test')->save();

    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder', 'display_builder_test']);
  }

  /**
   * The blank starting point seeds nothing.
   */
  public function testBlankIsEmpty(): void {
    self::assertSame([], $this->getInitialSources(StartingPointType::Blank));
  }

  /**
   * The minimal starting point seeds the affordance of a Drupal page.
   */
  public function testMinimalKeepsDrupalAffordance(): void {
    $sources = $this->getInitialSources(StartingPointType::Minimal);

    $expected = [
      'block:system_breadcrumb_block',
      'block:system_messages_block',
      'page_title',
      'block:local_tasks_block',
      'block:local_actions_block',
      'main_page_content',
    ];
    self::assertSame($expected, self::summarize($sources));
  }

  /**
   * The theme page shell is a seed of the import, never of the minimal page.
   */
  public function testOnlyTheImportSeedsTheThemePageShell(): void {
    $minimal = self::summarize($this->getInitialSources(StartingPointType::Minimal));
    self::assertNotContains('page_layout', $minimal);

    $imported = self::summarize($this->getInitialSources(StartingPointType::Theme));
    self::assertSame(['page_layout'], $imported);
  }

  /**
   * Without a starting point, an already seeded layout keeps its sources.
   */
  public function testNoStartingPointKeepsStoredSources(): void {
    $sources = [['source_id' => 'page_title', 'source' => []]];
    $this->createPageLayout('built_layout', $sources);

    self::assertSame(['page_title'], self::summarize($this->getInitialSources(NULL, 'built_layout')));
  }

  /**
   * Gets the sources a layout is seeded with.
   *
   * @param \Drupal\display_builder_page_layout\StartingPointType|null $starting_point
   *   The way the layout is seeded.
   * @param string|null $entity_id
   *   (Optional) The ID of a stored page layout, or NULL for a new one.
   *
   * @return array
   *   A list of sources.
   */
  private function getInitialSources(?StartingPointType $starting_point, ?string $entity_id = NULL): array {
    $entity = $entity_id === NULL ? PageLayout::create(['id' => 'seeded', 'label' => 'Seeded']) : PageLayout::load($entity_id);
    /** @var \Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout $buildable */
    $buildable = \Drupal::service('plugin.manager.display_buildable')->createInstance('page_layout', ['entity' => $entity]);

    return $buildable->getInitialSources($starting_point);
  }

  /**
   * Creates a page layout entity.
   *
   * @param string $id
   *   The entity ID.
   * @param array $sources
   *   The sources tree.
   */
  private function createPageLayout(string $id, array $sources): void {
    PageLayout::create([
      'id' => $id,
      'label' => $id,
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
      DisplayBuildableInterface::SOURCES_PROPERTY => $sources,
      'conditions' => [],
    ])->save();
  }

  /**
   * Reduces a sources list to readable identifiers.
   *
   * @param array $sources
   *   A list of sources.
   *
   * @return array
   *   The source IDs, suffixed by the block plugin ID for block sources.
   */
  private static function summarize(array $sources): array {
    return \array_map(static function (array $source): string {
      $source_id = $source['source_id'];

      if ($source_id === 'block') {
        return \sprintf('block:%s', $source['source']['plugin_id']);
      }

      return $source_id;
    }, $sources);
  }

}
