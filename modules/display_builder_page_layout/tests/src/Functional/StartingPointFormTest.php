<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Functional;

use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_page_layout\Form\PageLayoutForm;
use Drupal\display_builder_page_layout\PageLayoutListBuilder;
use Drupal\display_builder_page_layout\StartingPointType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the starting point chooser on the page layout creation form.
 *
 * @internal
 */
#[CoversClass(PageLayoutForm::class)]
#[CoversClass(PageLayoutListBuilder::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class StartingPointFormTest extends BrowserTestBase {

  public const PROFILE_ID = 'test_base';

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
    $admin_user = $this->createUser([], 'test_db_page_layout', TRUE);
    $this->drupalLogin($admin_user);
  }

  /**
   * The chooser seeds the layout, and stops offering the import afterwards.
   */
  public function testStartingPointSeedsTheLayoutOnce(): void {
    // The default layout is the adoption moment: all three options, and no
    // conditions to configure.
    $this->drupalGet('admin/structure/page-layout/add-default');
    $this->assertSession()->fieldExists('starting_point');
    $this->assertSession()->pageTextContains('Start from your current site');
    $this->assertSession()->pageTextContains('Minimal Drupal page');
    $this->assertSession()->fieldNotExists('conditions[request_path][pages]');

    $this->submitForm([
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Minimal->value,
    ], 'Save');

    $default = PageLayout::load(PageLayoutForm::DEFAULT_ID);
    self::assertNotNull($default);
    self::assertTrue($default->isDefault());
    $source_ids = \array_column($default->getSources(), 'source_id');
    self::assertContains('page_title', $source_ids);
    self::assertContains('main_page_content', $source_ids);
    // The minimal page never carries the theme page shell.
    self::assertNotContains('page_layout', $source_ids);

    // The import is a ramp: once a layout is built, it is gone for good, and
    // it is never offered on a conditional layout.
    $this->drupalGet('admin/structure/page-layout/add');
    $this->assertSession()->pageTextNotContains('Start from your current site');
    $this->assertSession()->pageTextContains('To start from a layout you already built');

    $this->submitForm([
      'label' => 'Second layout',
      'id' => 'second_layout',
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Blank->value,
      'conditions[request_path][pages]' => '/news',
    ], 'Save');

    $second = PageLayout::load('second_layout');
    self::assertNotNull($second);
    self::assertSame([], $second->getSources());
    self::assertFalse($second->isDefault());
  }

  /**
   * The site holds one default layout, and the route is the only way in.
   */
  public function testTheDefaultLayoutIsOfferedOnce(): void {
    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->linkExists('Create the default page layout');

    $this->drupalGet('admin/structure/page-layout/add-default');
    // The single default layout is named for the user, not by them.
    $this->assertSession()->fieldNotExists('label');
    $this->assertSession()->fieldNotExists('id');

    $this->submitForm([
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Blank->value,
    ], 'Save');
    $default = PageLayout::load(PageLayoutForm::DEFAULT_ID);
    self::assertTrue($default?->isDefault());
    self::assertSame(PageLayoutForm::DEFAULT_LABEL, $default->label());

    // A layout with no condition matches every page, so there is only room for
    // one. The action link goes with the access it checks.
    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->linkNotExists('Create the default page layout');
    $this->drupalGet('admin/structure/page-layout/add-default');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * The list says what still builds the pages the layouts do not cover.
   */
  public function testUncoveredPagesMessage(): void {
    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->pageTextContains('Create the default page layout to take them over.');

    // Created but empty: work started, not finished.
    $this->drupalGet('admin/structure/page-layout/add-default');
    $this->submitForm([
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Blank->value,
    ], 'Save');
    $this->assertSession()->pageTextContains('is empty, so pages matched by no layout below are still built by');

    $default = PageLayout::load(PageLayoutForm::DEFAULT_ID);
    self::assertNotNull($default);
    $default->setSources([['source_id' => 'page_title', 'source' => []]]);
    $default->setStatus(FALSE);
    $default->save();

    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->pageTextContains('is disabled, so pages matched by no layout below are still built by');

    $default->setStatus(TRUE);
    $default->save();

    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->pageTextNotContains('are still built by');
  }

  /**
   * With several default layouts, one working is enough, and any one counts.
   *
   * The UI offers a single default layout, but code can create more, and sites
   * in production already have. The one they use may not be the first.
   */
  public function testUncoveredPagesMessageWithSeveralDefaults(): void {
    // The first is empty, so on its own it covers nothing.
    $this->createDefaultLayout('first_default', []);
    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->pageTextContains('is empty, so pages matched by no layout below are still built by');

    // A second, disabled: still nothing covers those pages, and neither label
    // is the answer on its own.
    $second = $this->createDefaultLayout('second_default', [['source_id' => 'page_title', 'source' => []]]);
    $second->setStatus(FALSE);
    $second->save();

    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->pageTextContains('No default page layout is both enabled and built');

    // Enabling the second is enough, even though the first is still empty.
    $second->setStatus(TRUE);
    $second->save();

    $this->drupalGet('admin/structure/page-layout');
    $this->assertSession()->pageTextNotContains('are still built by');
  }

  /**
   * A conditional layout without a condition is refused, not saved.
   */
  public function testConditionalLayoutRequiresCondition(): void {
    $this->drupalGet('admin/structure/page-layout/add');
    $this->submitForm([
      'label' => 'No condition',
      'id' => 'no_condition',
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Blank->value,
    ], 'Save');

    $this->assertSession()->pageTextContains('Configure at least one condition.');
    self::assertNull(PageLayout::load('no_condition'));

    $this->submitForm([
      'conditions[request_path][pages]' => '/news',
    ], 'Save');

    $layout = PageLayout::load('no_condition');
    self::assertNotNull($layout);
    self::assertFalse($layout->isDefault());
  }

  /**
   * Editing keeps a layout on the side it was created on.
   */
  public function testEditingCannotConvertLayout(): void {
    $this->drupalGet('admin/structure/page-layout/add-default');
    $this->submitForm([
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Blank->value,
    ], 'Save');

    // The default layout is never offered a condition, so it cannot acquire
    // one and stop catching the pages nothing else matches.
    $this->drupalGet('admin/structure/page-layout/' . PageLayoutForm::DEFAULT_ID);
    $this->assertSession()->fieldNotExists('conditions[request_path][pages]');
    $this->assertSession()->pageTextContains('This layout has no condition');

    $this->drupalGet('admin/structure/page-layout/add');
    $this->submitForm([
      'label' => 'News layout',
      'id' => 'news_layout',
      'profile' => self::PROFILE_ID,
      'starting_point' => StartingPointType::Blank->value,
      'conditions[request_path][pages]' => '/news',
    ], 'Save');

    // A conditional layout cannot be stripped back into a second default.
    $this->drupalGet('admin/structure/page-layout/news_layout');
    $this->submitForm(['conditions[request_path][pages]' => ''], 'Save');
    $this->assertSession()->pageTextContains('Configure at least one condition.');
    self::assertFalse(PageLayout::load('news_layout')?->isDefault());
  }

  /**
   * Creates a page layout carrying no condition.
   *
   * @param string $id
   *   The entity ID.
   * @param array $sources
   *   The sources tree.
   *
   * @return \Drupal\display_builder_page_layout\Entity\PageLayout
   *   The saved page layout.
   */
  private function createDefaultLayout(string $id, array $sources): PageLayout {
    $page_layout = PageLayout::create([
      'id' => $id,
      'label' => $id,
      DisplayBuildableInterface::PROFILE_PROPERTY => self::PROFILE_ID,
      DisplayBuildableInterface::SOURCES_PROPERTY => $sources,
      'conditions' => [],
    ]);
    $page_layout->save();

    return $page_layout;
  }

}
