<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Functional;

use Drupal\display_builder\Controller\ApiPreviewController;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests previewing an entity view override on the entity's own page.
 *
 * An override belongs to one entity, so the entity's canonical page is where
 * it appears. Previewing there runs the real page pipeline: the Page Layout
 * the path resolves to, the real title, the real entity template.
 *
 * The half that must never regress is the second assertion of each test: the
 * draft is visible in the preview and nowhere else. The canonical URL carries
 * no flag that could make it serve anybody's unsaved work, and no cache entry
 * written while previewing may leak it either.
 *
 * @internal
 */
#[CoversClass(ApiPreviewController::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class EntityOverridePreviewTest extends BrowserTestBase {

  /**
   * The name of the field storing the override.
   */
  private const OVERRIDE_FIELD = 'field_db_override';

  /**
   * The profile the fixture display is built with.
   */
  private const PROFILE_ID = 'test_builder';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'entity_test',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_ui',
    'display_builder_test',
    'display_builder_entity_view',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'display_builder_theme_test';

  /**
   * The entity carrying the override.
   */
  private EntityTest $entity;

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

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'full',
    ]);
    $display
      ->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY, self::PROFILE_ID)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, self::PROFILE_ID)
      ->save();

    $this->entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => 'Overridden entity',
    ]);
    $this->entity->save();

    $this->drupalLogin($this->createUser([], 'test_db_override_preview', TRUE));
  }

  /**
   * The override previews on the entity page, showing the unsaved draft.
   */
  public function testOverridePreviewsOnTheEntityPageWithTheDraft(): void {
    $marker = 'Unsaved override marker';
    $instance = $this->createInstance();
    $instance->attachToRoot(0, 'textfield', ['value' => $marker]);
    $instance->save();

    $path = '/entity_test/' . $this->entity->id();

    // Warm the caches for this page first, the same way an ordinary visitor
    // would. The preview renders it in a sub-request, and neither the page
    // caches nor the entity's own render cache may hand it back what they
    // stored here.
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($marker);

    $this->drupalGet('display-builder/preview/' . $instance->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($marker);

    // The same page, requested normally, still serves the saved override - and
    // the preview must not have left the draft in a cache it reads.
    $this->drupalGet($path);
    $this->assertSession()->pageTextNotContains($marker);

    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->pageTextNotContains($marker);
  }

  /**
   * Creates the override instance for the fixture entity.
   *
   * @return \Drupal\display_builder\InstanceInterface
   *   The instance.
   */
  private function createInstance(): object {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = \Drupal::service('plugin.manager.display_buildable')->createInstance('entity_view_override', [
      'display' => EntityViewDisplay::load('entity_test.entity_test.full'),
      'entity' => $this->entity,
    ]);
    $buildable->initInstanceIfMissing();

    return $buildable->getInstance();
  }

}
