<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the page a view display can be previewed on.
 *
 * Only a display owning one concrete route has an unambiguous page to preview
 * against. Getting this wrong in the permissive direction would send the
 * preview to a page the display does not serve, or to a route with an
 * argument nothing here can fill.
 *
 * @internal
 */
#[CoversClass(ViewDisplay::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
#[RunTestsInSeparateProcesses]
final class ViewDisplayPreviewPagePathTest extends KernelTestBase {

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
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'views', 'display_builder', 'display_builder_views', 'ui_patterns']);

    // ::getExtender() reads the display's extenders, and views only builds
    // those it is told about. display_builder_views_install() does this on a
    // real site; a kernel test installs config without running it.
    $this->config('views.settings')->set('display_extenders', ['display_builder'])->save();
  }

  /**
   * A display names the page it owns, or nothing at all.
   *
   * @param string $display_plugin
   *   The views display plugin ID.
   * @param string|null $path
   *   The display's path option, or NULL to leave it unset.
   * @param string|null $expected
   *   The path the display should offer to preview on.
   */
  #[DataProvider('providerPreviewPagePath')]
  public function testPreviewPagePath(string $display_plugin, ?string $path, ?string $expected): void {
    self::assertSame($expected, $this->getPreviewPagePath($display_plugin, $path));
  }

  /**
   * Cases for ::testPreviewPagePath().
   *
   * @return array
   *   Test cases, keyed by what the display looks like.
   */
  public static function providerPreviewPagePath(): array {
    return [
      'a page' => ['page', 'test/listing', '/test/listing'],
      'a page, path already rooted' => ['page', '/test/listing', '/test/listing'],
      'a page with surrounding blanks' => ['page', '  test/listing  ', '/test/listing'],
      'a page taking an argument' => ['page', 'test/%/listing', NULL],
      'a page with no path' => ['page', NULL, NULL],
      'a page with an empty path' => ['page', '', NULL],
      // A feed carries a path too, and is the one other display type that
      // does, but there is no page to look at behind it.
      'a feed' => ['feed', 'test/listing.xml', NULL],
      'the default display' => ['default', NULL, NULL],
      'a block' => ['block', NULL, NULL],
    ];
  }

  /**
   * Gets the page a display of this shape would be previewed on.
   *
   * @param string $display_plugin
   *   The views display plugin ID.
   * @param string|null $path
   *   The display's path option, or NULL to leave it unset.
   *
   * @return string|null
   *   The display's own page, or NULL when it owns none.
   */
  private function getPreviewPagePath(string $display_plugin, ?string $path): ?string {
    $display_id = $display_plugin === 'default' ? 'default' : $display_plugin . '_1';
    $display_options = [];

    if ($path !== NULL) {
      $display_options['path'] = $path;
    }
    $displays = [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Master',
        'position' => 0,
        'display_options' => [],
      ],
    ];

    if ($display_id !== 'default') {
      $displays[$display_id] = [
        'display_plugin' => $display_plugin,
        'id' => $display_id,
        'display_title' => $display_plugin,
        'position' => 1,
        'display_options' => $display_options,
      ];
    }
    $view = View::create([
      'id' => 'test_preview_path',
      'label' => 'Test preview path',
      'base_table' => 'users_field_data',
      'display' => $displays,
    ]);
    $view->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance('view_display', [
      'view_id' => 'test_preview_path',
      'view_display' => $display_id,
    ]);

    return $buildable->getPreviewPagePath();
  }

}
