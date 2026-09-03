<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Functional;

use Drupal\display_builder\Controller\ApiPreviewController;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests previewing a view page display on the page it owns.
 *
 * A page display has one route, so the preview runs the real page pipeline
 * there: the real title, the real theme, the real view results.
 *
 * The half that must never regress is the second assertion: the draft shows in
 * the preview and nowhere else. The public path carries no flag that could
 * make it serve somebody's unsaved work, and previewing may not leave the
 * draft in a cache an ordinary visitor reads - views caches its own output.
 *
 * @internal
 */
#[CoversClass(ApiPreviewController::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
#[RunTestsInSeparateProcesses]
final class ViewPagePreviewTest extends BrowserTestBase {

  /**
   * The path of the fixture view's page display.
   */
  private const PATH = '/test-db-view-render';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'views',
    'views_ui',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_views',
    'display_builder',
    'display_builder_ui',
    'display_builder_test',
    'display_builder_views',
    'display_builder_views_test',
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
    $this->grantPermissions(
      $this->container->get('entity_type.manager')->getStorage('user_role')->load('anonymous'),
      ['access content']
    );
    $this->drupalLogin($this->createUser([], 'test_db_view_preview', TRUE));
  }

  /**
   * The page display previews on its own path, showing the unsaved draft.
   */
  public function testViewPagePreviewsOnItsPathWithTheDraft(): void {
    $marker = 'Unsaved view marker';
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance('view_display', [
      'view_id' => 'test_db_view_render',
      'view_display' => 'page_1',
    ]);
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();
    $instance->attachToRoot(0, 'textfield', ['value' => $marker]);
    $instance->save();

    // Warm the caches for this page the way an ordinary visitor would. Neither
    // the page caches, nor the render cache, nor views' own output cache may
    // hand the preview back what they stored here.
    $this->drupalGet(self::PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($marker);

    $this->drupalGet('display-builder/preview/' . $instance->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($marker);

    // The same page, requested normally, still serves what the view has
    // stored - and the preview must not have left the draft behind.
    $this->drupalGet(self::PATH);
    $this->assertSession()->pageTextNotContains($marker);

    $this->drupalLogout();
    $this->drupalGet(self::PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($marker);
  }

}
