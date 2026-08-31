<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Functional;

use Drupal\display_builder\Controller\ApiPreviewController;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests previewing a page layout on the real page it is pinned to.
 *
 * A page's own main content and title are substituted by the page pipeline, not
 * by a source plugin, so a preview that renders sources on their own can only
 * show a placeholder for them. A layout pinned to one concrete path is instead
 * previewed by sub-requesting that path, which runs the real pipeline and
 * serves this instance's unsaved draft.
 *
 * The half that must never regress is the second test: the draft is visible in
 * the preview and nowhere else. The pinned page's public URL carries no flag
 * that could make it render anybody's unsaved work.
 *
 * @internal
 */
#[CoversClass(ApiPreviewController::class)]
#[CoversClass(PageLayoutPageVariant::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class PinnedPagePreviewTest extends BrowserTestBase {

  /**
   * The profile the fixture layouts are built with.
   */
  private const PROFILE_ID = 'test_builder';

  /**
   * The main content placeholder a source-only preview emits.
   *
   * Its help sentence, not its name: assertions on page text are case
   * insensitive, and "Main content" matches core's own "Skip to main content"
   * link on every page, which would pass and fail this for the wrong reason.
   *
   * @see \Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\MainPageContentSource
   */
  private const PLACEHOLDER = 'This placeholder will be replaced by the page value.';

  /**
   * The main content the pinned page really serves.
   *
   * @see \Drupal\display_builder_page_layout_test\Controller\TestPageController
   */
  private const REAL_CONTENT = 'Page layout test page: pinned';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_ui',
    'display_builder_test',
    'display_builder_page_layout',
    'display_builder_page_layout_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'display_builder_theme_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createUser([], 'test_db_preview', TRUE));
  }

  /**
   * A pinned layout previews as its real page, showing the unsaved draft.
   */
  public function testPinnedLayoutPreviewsItsRealPageWithTheDraft(): void {
    $this->createLayout('pinned', '/test/pinned');

    $marker = 'Unsaved draft marker';
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = \Drupal::entityTypeManager()->getStorage('display_builder_instance')->load('page_layout__pinned');
    $instance->attachToRoot(0, 'textfield', ['value' => $marker]);
    $instance->save();

    // Warm Dynamic Page Cache for this page first. The preview renders it in a
    // sub-request, and Dynamic Page Cache has no main-request guard, so without
    // DenyPreviewSubRequest what comes back here is this cached render: the
    // saved display, with none of the edits below.
    // @see \Drupal\display_builder\PageCache\DenyPreviewSubRequest
    $this->drupalGet('test/pinned');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('display-builder/preview/page_layout__pinned');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(self::REAL_CONTENT);
    $this->assertSession()->pageTextNotContains(self::PLACEHOLDER);
    $this->assertSession()->pageTextContains($marker);

    // The same page, requested normally, still serves the saved layout - the
    // draft has no way onto it.
    $this->drupalGet('test/pinned');
    $this->assertSession()->pageTextContains(self::REAL_CONTENT);
    $this->assertSession()->pageTextNotContains($marker);

    $this->drupalLogout();
    $this->drupalGet('test/pinned');
    $this->assertSession()->pageTextNotContains($marker);
  }

  /**
   * A layout matching many pages keeps the source-only preview.
   */
  public function testUnpinnedLayoutPreviewsItsSources(): void {
    $this->createLayout('unpinned', '/test/*');

    $this->drupalGet('display-builder/preview/page_layout__unpinned');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(self::PLACEHOLDER);
    $this->assertSession()->pageTextNotContains(self::REAL_CONTENT);
  }

  /**
   * Creates a layout bound to a path, and its builder instance.
   *
   * @param string $id
   *   The layout ID.
   * @param string $pages
   *   The request_path condition's page list.
   */
  private function createLayout(string $id, string $pages): void {
    $layout = PageLayout::create([
      'id' => $id,
      'label' => $id,
      DisplayBuildableInterface::PROFILE_PROPERTY => self::PROFILE_ID,
      DisplayBuildableInterface::SOURCES_PROPERTY => [
        [
          'node_id' => 'a1b2c3d4e5f60718',
          'source_id' => 'main_page_content',
          'source' => [],
        ],
      ],
      'conditions' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => FALSE,
          'pages' => $pages,
        ],
      ],
    ]);
    $layout->save();

    \Drupal::service('plugin.manager.display_buildable')
      ->createInstance('page_layout', ['entity' => $layout])
      ->initInstanceIfMissing();
  }

}
