<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Functional;

use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the migration from manage display and layout builder config.
 *
 * This is not testing the migration itself. This must be done in a expected
 * \Drupal\Tests\display_builder_entity_view\Unit\BuilderDataConverterTest.
 *
 * @internal
 */
#[CoversClass(LayoutBuilderEntityViewDisplay::class)]
#[CoversClass(EntityViewDisplay::class)]
#[CoversClass(EntityViewDisplayTrait::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class LayoutBuilderConfigMigrationTest extends BrowserTestBase {

  public const PROFILE_ID = 'default';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'layout_builder',
    'layout_discovery',
    'ui_patterns',
    'ui_patterns_layouts',
    'display_builder',
    'display_builder_test',
    'display_builder_entity_view',
    'display_builder_entity_view_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in user.
    $admin_user = $this->drupalCreateUser([
      'administer node display',
      'access administration pages',
      'create display_builder_test content',
      'use display builder ' . self::PROFILE_ID,
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Test the Layout Builder initial config import.
   */
  public function testLayoutBuilderConfigImport(): void {
    // Enable Display Builder.
    $edit = ['profile' => self::PROFILE_ID];
    $this->drupalGet('admin/structure/types/manage/display_builder_test/display/display_builder_test');
    $this->submitForm($edit, 'Save');

    $this->drupalGet('admin/structure/types/manage/display_builder_test/display/display_builder_test/display-builder');

    // Test Display Builder migrated from the Layout Builder configuration.
    $this->assertSession()->elementTextContains('css', '.db-island-preview', 'Layout builder config: Default');
  }

}
