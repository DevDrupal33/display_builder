<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Functional;

use Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\MainPageContentSource;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the main content placeholder, and that it never reaches a real page.
 *
 * The placeholder turns the page layout's one dead end into something that
 * says what owns it. The whole design rests on the page variant replacing the
 * source node before any source plugin runs, so the regression worth guarding
 * is a real page showing the placeholder instead of its content.
 *
 * @internal
 */
#[CoversClass(MainPageContentSource::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class MainContentPlaceholderTest extends BrowserTestBase {

  /**
   * The wording shown in the builder where no link is possible.
   */
  private const PLACEHOLDER = 'Main content';

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
   * A rendered page shows its own content, never the placeholder.
   */
  public function testRealPageRendersItsContentNotThePlaceholder(): void {
    $this->drupalGet('/test/default');
    $this->assertSession()->statusCodeEquals(200);

    // The layout is what painted this page, not the bare controller output.
    $this->assertSession()->pageTextContains('Text 1');

    // The page variant substituted the real main content for the source node.
    $this->assertSession()->pageTextContains('Page layout test page: default');
    $this->assertSession()->pageTextNotContains('Filled by the display of whatever page');
    $this->assertSession()->elementNotExists('css', '.db-placeholder-region');
  }

  /**
   * A title that is a render array is still the page's own title.
   *
   * A _title_callback may return anything renderable, and the layout has no
   * markup to make out of that. What it must not do is leave the title node
   * unmarked, because unmarked means "nothing filled this" and a real page
   * then shows its visitors the builder's placeholder.
   */
  public function testArrayPageTitleIsNotReplacedByThePlaceholder(): void {
    $this->drupalGet('/test/array-title');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('Linked page title');
    $this->assertSession()->pageTextNotContains('Filled by the title of whatever page');
    $this->assertSession()->elementNotExists('css', '.db-placeholder-region[data-testid="page_title"]');
  }

  /**
   * The builder shows the placeholder, and says no link is possible.
   */
  public function testBuilderShowsThePlaceholder(): void {
    $admin_user = $this->createUser([], 'test_db_placeholder', TRUE);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/page-layout/test_default_page/builder');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains(self::PLACEHOLDER);
    // A region standing for the page's whole content area, not a control. The
    // layout has more than one region placeholder, so name the node: the page
    // title is one too and comes first.
    $placeholder = $this->assertSession()->elementExists('css', '.db-placeholder-region[data-testid="main_page_content"]');
    $text = $placeholder->getText();
    self::assertStringContainsString(self::PLACEHOLDER, $text);
    self::assertStringContainsString('This placeholder will be replaced by the page value.', $text);
  }

}
