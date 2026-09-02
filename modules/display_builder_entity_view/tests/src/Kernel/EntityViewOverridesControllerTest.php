<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Routing\RouteMatch;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder_entity_view\Controller\EntityViewOverridesController;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Test the Entity View Overrides controller's access cacheability.
 *
 * Reproduces #3616546: an override's local task must depend on the
 * entity_view_display config it reads, so enabling "Enable content
 * overrides" later invalidates a page that cached the tab as forbidden.
 *
 * @internal
 */
#[CoversClass(EntityViewOverridesController::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class EntityViewOverridesControllerTest extends EntityKernelTestBase {

  /**
   * The name of the field storing the override.
   */
  private const OVERRIDE_FIELD = 'field_db_override';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'display_builder',
    'display_builder_entity_view',
    'display_builder_test',
    'ui_patterns',
    'ui_patterns_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['display_builder', 'display_builder_test']);
    $this->installEntitySchema('display_builder_instance');

    EntityViewMode::create([
      'id' => 'entity_test.full',
      'label' => 'Full',
      'targetEntityType' => 'entity_test',
    ])->save();

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
  }

  /**
   * A forbidden result must depend on the display, not just the route.
   *
   * The field is set - so the local task and its route exist, the realistic
   * state once "Enable content overrides" has been saved at least once -
   * but the profile is not, so the display is not yet overridable and
   * access is forbidden. That forbidden result must still carry the
   * display's own cache tags, or a page cached here never learns that a
   * later save making the display overridable should invalidate it.
   */
  public function testCheckAccessForbiddenDependsOnDisplayConfig(): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'full',
    ]);
    $display->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->save();

    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Test entity']);
    $entity->save();

    $account = $this->setUpCurrentUser();
    $controller = EntityViewOverridesController::create($this->container);
    $access = $controller->checkAccess($this->routeMatch($entity), $account);

    self::assertFalse($access->isAllowed());
    self::assertEmpty(\array_diff($display->getCacheTags(), $access->getCacheTags()));
  }

  /**
   * An allowed result must depend on both the display and the permission.
   */
  public function testCheckAccessAllowedDependsOnDisplayConfigAndPermission(): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'full',
    ]);
    $display->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->save();

    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Test entity']);
    $entity->save();

    $account = $this->setUpCurrentUser([], ['use display builder test_base']);
    $controller = EntityViewOverridesController::create($this->container);
    $access = $controller->checkAccess($this->routeMatch($entity), $account);

    self::assertTrue($access->isAllowed());
    self::assertContains('user.permissions', $access->getCacheContexts());
    self::assertEmpty(\array_diff($display->getCacheTags(), $access->getCacheTags()));
  }

  /**
   * The level-0 shortcut must inherit the cacheability it was decided on.
   *
   * ::checkFirstBuilderAccess() delegates to ::getFirstOverridableViewMode(),
   * which examines the same forbidden display as above. The aggregated
   * result must still carry that display's cache tags, or the "Display" tab
   * itself - not just its child tabs - stays stuck forbidden after the
   * display becomes overridable.
   */
  public function testCheckFirstBuilderAccessDependsOnDisplayConfig(): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'full',
    ]);
    $display->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->save();

    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Test entity']);
    $entity->save();

    $account = $this->setUpCurrentUser();
    $controller = EntityViewOverridesController::create($this->container);
    $access = $controller->checkFirstBuilderAccess($this->routeMatch($entity), $account);

    self::assertFalse($access->isAllowed());
    self::assertEmpty(\array_diff($display->getCacheTags(), $access->getCacheTags()));
  }

  /**
   * The route match ::checkAccess() and ::checkFirstBuilderAccess() expect.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity the override display belongs to.
   *
   * @return \Drupal\Core\Routing\RouteMatch
   *   A route match carrying the parameters both access callbacks read.
   */
  private function routeMatch($entity): RouteMatch {
    $route = new Route('/entity_test/{entity_test}/display/full', [
      'entity_type_id' => 'entity_test',
      'view_mode_name' => 'full',
    ]);

    return new RouteMatch('entity.entity_test.display_builder.full', $route, [
      'entity_type_id' => 'entity_test',
      'entity_test' => $entity,
    ]);
  }

}
