<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the Entity View Override buildable plugin.
 *
 * @internal
 */
#[CoversClass(EntityViewOverride::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class EntityViewOverrideTest extends EntityKernelTestBase {

  /**
   * The name of the field storing the override.
   */
  private const OVERRIDE_FIELD = 'field_db_override';

  /**
   * The display buildable manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

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
    $this->displayBuildableManager = $this->container->get('plugin.manager.display_buildable');

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
   * Test the ::getDisplayLabel method.
   *
   * An override is named after the entity it belongs to, not just its bundle:
   * two overrides of the same view mode share a bundle and a view mode, so the
   * entity ID and label are the only things telling them apart in a listing.
   */
  public function testGetDisplayLabel(): void {
    EntityViewMode::create([
      'id' => 'entity_test.full',
      'label' => 'Full',
      'targetEntityType' => 'entity_test',
    ])->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'full',
    ]);
    $display
      ->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->save();

    $entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => 'My test entity',
    ]);
    $entity->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('entity_view_override', [
      'display' => $display,
      'entity' => $entity,
    ]);

    self::assertSame('Entity view override', $buildable->label());
    self::assertSame(
      \sprintf('Entity Test Bundle [%s] (Full)', $entity->id()),
      $buildable->getDisplayLabel(),
    );
  }

}
