<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Functional;

use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplayTrait;
use Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay;
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
final class LayoutBuilderOverrideMigrationTest extends BrowserTestBase {

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
    'display_builder_entity_view_override_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Create and log in user.
    $admin_user = $this->drupalCreateUser([
      'administer node display',
      'access administration pages',
      'create display_builder_test content',
      'edit any display_builder_test content',
      'use display builder ' . self::PROFILE_ID,
      'configure all display_builder_test node layout overrides',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Test the Layout Builder initial config import.
   */
  public function testLayoutBuilderConfigImport(): void {
    // Create Layout Builder override for a node.
    // Node from config has already a layout builder field.
    $node = $this->createNode([
      'type' => 'display_builder_test',
      'title' => 'Test layout builder override',
    ]);
    $nid = $node->id();

    $this->drupalGet('node/' . $nid . '/layout');

    $this->clickLink('Configure Section 1');
    $edit = ['layout_settings[ui_patterns][props][prop_string][source][value]' => 'Layout builder OVERRIDE config: NEW'];
    $this->submitForm($edit, 'Update');
    $this->submitForm([], 'Save layout');

    $this->assertSession()->pageTextContains('Layout builder OVERRIDE config: NEW');

    // Enable Display Builder override.
    $this->drupalGet('admin/structure/types/manage/display_builder_test/display/default');
    $edit = [
      'profile' => self::PROFILE_ID,
      'override_status' => 1,
      'override_profile' => self::PROFILE_ID,
    ];
    $this->submitForm($edit, 'Save');

    // Test Display Builder migrated from the Layout Builder configuration.
    $this->drupalGet('node/' . $nid . '/display/default');
    $this->assertSession()->elementTextContains('css', '.db-island-preview', 'Layout builder OVERRIDE config: NEW');
  }

}
