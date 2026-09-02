<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Functional;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder_entity_view\Plugin\Derivative\EntityOverrideViewLocalTask;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the flat, per-display local tabs core Navigation renders.
 *
 * Reproduces #3616546's second half without any custom rendering: since
 * every overridable display is its own top-level tab
 * (EntityOverrideViewLocalTask), core Navigation's own generic "Page
 * Actions" top bar item - which only ever fetches level-0 local tasks -
 * already lists every one of them, and already excludes whichever one is
 * the current route on its own.
 *
 * @internal
 */
#[CoversClass(EntityOverrideViewLocalTask::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class OverrideDisplayTabsTest extends BrowserTestBase {

  /**
   * The name of the field storing the override.
   */
  private const OVERRIDE_FIELD = 'field_db_override';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'navigation',
    'node',
    'display_builder',
    'display_builder_ui',
    'display_builder_entity_view',
    'display_builder_test',
    'ui_patterns',
    'ui_patterns_field',
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

    FieldStorageConfig::create([
      'field_name' => self::OVERRIDE_FIELD,
      'entity_type' => 'entity_test',
      'type' => 'ui_patterns_source',
    ])->save();
    FieldConfig::create([
      'field_name' => self::OVERRIDE_FIELD,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Display Builder override',
    ])->save();

    foreach (['teaser', 'full'] as $view_mode) {
      if (!EntityViewMode::load('entity_test.' . $view_mode)) {
        EntityViewMode::create([
          'id' => 'entity_test.' . $view_mode,
          'label' => \ucfirst($view_mode),
          'targetEntityType' => 'entity_test',
        ])->save();
      }

      $this->container->get('entity_type.manager')->getStorage('entity_view_display')->create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => $view_mode,
      ])
        ->setStatus(TRUE)
        ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
        ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
        ->save();
    }
    // Both the entity type's extra link templates
    // (Navigation::entityTypeAlter() reads the view modes just created
    // above) and the override routes (OverridesRoutes::buildRoutes()) are
    // normally kept in sync by the real Manage Display form's own submit
    // handler - creating them directly here does not, so both caches need
    // an explicit rebuild.
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('router.builder')->rebuild();

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
  }

  /**
   * Every overridable display shows up as its own tab.
   */
  public function testEachDisplayHasItsOwnTab(): void {
    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Tabs test entity']);
    $entity->save();

    $this->drupalGet(\sprintf('/entity_test/%d', $entity->id()));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Display: Full');
    $this->assertSession()->linkExists('Display: Teaser');
  }

  /**
   * The display already open is excluded, core Navigation's own doing.
   */
  public function testCurrentDisplayIsExcludedFromItsOwnList(): void {
    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Tabs test entity']);
    $entity->save();

    $this->drupalGet(\sprintf('/entity_test/%d/display/full', $entity->id()));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Display: Teaser');
    $this->assertSession()->linkNotExists('Display: Full');
  }

}
