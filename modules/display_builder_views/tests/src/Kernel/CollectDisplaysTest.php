<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the read-only view display listing behind the Instances panel.
 *
 * Same three concerns as the entity view counterpart: displays not built with
 * Display Builder are listed (they are the link in the chain users get stuck
 * on), listing writes nothing, and displays that are not a page level are
 * filtered out. The filtering is the part specific to Views: a view carries
 * displays that are not renderable output at all.
 *
 * @internal
 */
#[CoversClass(ViewDisplay::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
#[RunTestsInSeparateProcesses]
final class CollectDisplaysTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'views_ui',
    'display_builder',
    'display_builder_views',
    'display_builder_test',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('view');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'views', 'display_builder', 'display_builder_test', 'ui_patterns']);
    // Kernel tests do not run hook_install(), and that is where the extender
    // gets registered. Without it ::getDisplayLabel() answers NULL for every
    // display and the listing is empty.
    $this->config('views.settings')->set('display_extenders', ['display_builder'])->save();
  }

  /**
   * A display with no profile is listed, marked, and linked to the Views UI.
   *
   * A page built by a view is a level a page is assembled from whether or not
   * anybody opened it in Display Builder, so it belongs in the panel. Only the
   * link changes: the builder when built, the Views UI when not.
   */
  public function testListsDisplaysNotBuiltWithDisplayBuilder(): void {
    $this->createView('front_list', 'Front list', [
      'page_1' => $this->pageDisplay('Page', 'front-list'),
      'block_1' => [
        'display_plugin' => 'block',
        'display_title' => 'Block',
        'display_options' => $this->displayBuilderOptions('test_base'),
      ],
      'block_2' => [
        'display_plugin' => 'block',
        'display_title' => 'Filled block',
        'display_options' => $this->displayBuilderOptions('test_base', [
          'node-1' => ['source_id' => 'component'],
        ]),
      ],
    ]);

    $by_label = $this->collectDisplays('front_list');

    self::assertArrayHasKey('Front list (Page)', $by_label);
    $page = $by_label['Front list (Page)'];
    self::assertFalse($page->built);
    // Not being built is an action, not a status: ::built is what says so, and
    // the panel renders a "Build" link rather than a status word.
    self::assertNull($page->status());
    self::assertSame('Views', $page->kind);
    self::assertSame('views__front_list__page_1', $page->instanceId);
    self::assertStringContainsString(
      '/admin/structure/views/view/front_list/edit/page_1',
      $page->url->toString(),
    );

    self::assertArrayHasKey('Front list (Block)', $by_label);
    $empty = $by_label['Front list (Block)'];
    self::assertTrue($empty->built);
    self::assertSame('empty', $empty->status());
    self::assertStringContainsString(
      '/admin/structure/views/view/front_list/display-builder/block_1',
      $empty->url->toString(),
    );
    // The settings link keeps pointing at the Views UI even when built.
    self::assertStringContainsString(
      '/admin/structure/views/view/front_list/edit/block_1',
      $empty->settingsUrl->toString(),
    );

    self::assertArrayHasKey('Front list (Filled block)', $by_label);
    $filled = $by_label['Front list (Filled block)'];
    self::assertTrue($filled->built);
    self::assertNull($filled->status());
  }

  /**
   * Displays that are not renderable page output are not page levels.
   *
   * The default display has no UI of its own, a feed returns a response rather
   * than a themed page, and a disabled display renders nothing at all.
   */
  public function testSkipsDisplaysThatAreNotPageOutput(): void {
    $disabled = $this->pageDisplay('Disabled page', 'switched-off');
    $disabled['display_options']['enabled'] = FALSE;

    $this->createView('mixed', 'Mixed', [
      'page_1' => $this->pageDisplay('Page', 'mixed'),
      'page_2' => $disabled,
      'feed_1' => [
        'display_plugin' => 'feed',
        'display_title' => 'Feed',
        'display_options' => ['path' => 'mixed/rss.xml'],
      ],
    ]);

    self::assertSame(['Mixed (Page)'], \array_keys($this->collectDisplays('mixed')));
  }

  /**
   * Administrative displays are not page levels either.
   *
   * Both checks exist because a view display serving the admin UI is not a
   * front end page a user assembles, and listing it is noise pointing at a
   * builder that would style the wrong theme.
   */
  public function testSkipsAdministrativeDisplays(): void {
    $admin_theme = $this->pageDisplay('Admin theme page', 'reports/mine');
    $admin_theme['display_options']['use_admin_theme'] = TRUE;

    $this->createView('admin_views', 'Admin views', [
      'page_1' => $this->pageDisplay('Page', 'admin/content/things'),
      'page_2' => $admin_theme,
      'page_3' => $this->pageDisplay('Front page', 'things'),
    ]);

    self::assertSame(
      ['Admin views (Front page)'],
      \array_keys($this->collectDisplays('admin_views')),
    );
  }

  /**
   * A disabled view contributes no display at all.
   */
  public function testSkipsDisabledViews(): void {
    $view = $this->createView('turned_off', 'Turned off', [
      'page_1' => $this->pageDisplay('Page', 'turned-off'),
    ]);
    $view->disable()->save();

    self::assertSame([], $this->collectDisplays('turned_off'));
  }

  /**
   * Listing creates and saves nothing.
   *
   * Rendering a read-only navigation panel used to write instance rows nobody
   * asked for, on every build. This is the assertion that keeps it read-only.
   */
  public function testListingHasNoSideEffects(): void {
    $this->createView('side_effects', 'Side effects', [
      'page_1' => $this->pageDisplay('Page', 'side-effects'),
      'block_1' => [
        'display_plugin' => 'block',
        'display_title' => 'Block',
        'display_options' => $this->displayBuilderOptions('test_base'),
      ],
    ]);

    $storage = $this->container->get('entity_type.manager')->getStorage('display_builder_instance');
    $before = \count($storage->loadMultiple());

    $this->collectDisplays('side_effects');

    $storage->resetCache();
    self::assertSame($before, \count($storage->loadMultiple()));
  }

  /**
   * Collect the references of one view, keyed by label.
   *
   * Scoped to a single view because installing Views also installs the
   * optional views other modules ship, and those are collected too.
   *
   * @param string $view_id
   *   The view to keep.
   *
   * @return array<string, \Drupal\display_builder\DisplayReference>
   *   The references, keyed by display label.
   */
  private function collectDisplays(string $view_id): array {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance('view_display', []);
    $prefix = \sprintf('views__%s__', $view_id);
    $keyed = [];

    foreach ($buildable->collectDisplays() as $reference) {
      if (\str_starts_with($reference->instanceId, $prefix)) {
        $keyed[$reference->label] = $reference;
      }
    }

    return $keyed;
  }

  /**
   * Create a view, with the mandatory default display added for free.
   *
   * The default display is always present in a real view and is never listed,
   * so every case here gets one without having to say so.
   *
   * @param string $id
   *   The view id.
   * @param string $label
   *   The view label.
   * @param array<string, array<string, mixed>> $displays
   *   The displays to add, keyed by display id.
   *
   * @return \Drupal\views\Entity\View
   *   The saved view.
   */
  private function createView(string $id, string $label, array $displays): View {
    $all = [
      'default' => [
        'id' => 'default',
        'display_plugin' => 'default',
        'display_title' => 'Master',
        'position' => 0,
        'display_options' => [],
      ],
    ];
    $position = 0;

    foreach ($displays as $display_id => $display) {
      $all[$display_id] = $display + [
        'id' => $display_id,
        'position' => ++$position,
        'display_options' => [],
      ];
    }

    $view = View::create([
      'id' => $id,
      'label' => $label,
      'base_table' => 'user',
      'display' => $all,
    ]);
    $view->save();

    return $view;
  }

  /**
   * A page display definition.
   *
   * @param string $title
   *   The display title, which is what the panel labels the row with.
   * @param string $path
   *   The page path.
   *
   * @return array<string, mixed>
   *   The display definition.
   */
  private function pageDisplay(string $title, string $path): array {
    return [
      'display_plugin' => 'page',
      'display_title' => $title,
      'display_options' => ['path' => $path],
    ];
  }

  /**
   * Display options carrying the Display Builder extender settings.
   *
   * @param string $profile
   *   The profile id.
   * @param array<string, mixed> $sources
   *   The stored sources, empty for a built but empty display.
   *
   * @return array<string, mixed>
   *   The display options.
   */
  private function displayBuilderOptions(string $profile, array $sources = []): array {
    return [
      'display_extenders' => [
        'display_builder' => [
          DisplayBuildableInterface::PROFILE_PROPERTY => $profile,
          DisplayBuildableInterface::SOURCES_PROPERTY => $sources,
        ],
      ],
    ];
  }

}
