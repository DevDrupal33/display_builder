<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Plugin\Derivative\EntityOverrideViewLocalTask;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the local task labels the entity view override deriver produces.
 *
 * Every overridable display is its own flat, top-level tab - named after
 * the display it opens - rather than one generic "Display" tab hiding a
 * second level of its own underneath. Nothing here special-cases "one
 * display" versus "several" any more: every display's tab is shown the
 * same way, alongside View, Edit, Delete, Revisions.
 *
 * @internal
 */
#[CoversClass(EntityOverrideViewLocalTask::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class EntityOverrideViewLocalTaskTest extends EntityKernelTestBase {

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
   * A single overridable view mode is a flat tab, named after its display.
   */
  public function testSingleViewModeIsNamedAfterItsDisplay(): void {
    $this->createOverridableDisplay('full', 'Full content');

    $definitions = $this->getDerivatives();

    self::assertArrayNotHasKey('entity.entity_test.display_builder.forward', $definitions);
    self::assertSame('Display: Full content', (string) $definitions['entity.entity_test.display_builder.full']['title']);
    self::assertSame('entity.entity_test.canonical', $definitions['entity.entity_test.display_builder.full']['base_route']);
    self::assertArrayNotHasKey('parent_id', $definitions['entity.entity_test.display_builder.full']);
  }

  /**
   * Several overridable view modes are each their own flat tab too.
   */
  public function testMultipleViewModesAreEachTheirOwnFlatTab(): void {
    $this->createOverridableDisplay('full', 'Full content');
    $this->createOverridableDisplay('teaser', 'Teaser');

    $definitions = $this->getDerivatives();

    self::assertArrayNotHasKey('entity.entity_test.display_builder.forward', $definitions);
    self::assertSame('Display: Full content', (string) $definitions['entity.entity_test.display_builder.full']['title']);
    self::assertSame('Display: Teaser', (string) $definitions['entity.entity_test.display_builder.teaser']['title']);
    self::assertSame('entity.entity_test.canonical', $definitions['entity.entity_test.display_builder.full']['base_route']);
    self::assertSame('entity.entity_test.canonical', $definitions['entity.entity_test.display_builder.teaser']['base_route']);
    self::assertArrayNotHasKey('parent_id', $definitions['entity.entity_test.display_builder.full']);
    self::assertArrayNotHasKey('parent_id', $definitions['entity.entity_test.display_builder.teaser']);
  }

  /**
   * Creates an overridable entity_test view display for the given mode.
   *
   * @param string $view_mode
   *   The view mode machine name.
   * @param string $label
   *   The view mode's label.
   */
  private function createOverridableDisplay(string $view_mode, string $label): void {
    if (!EntityViewMode::load('entity_test.' . $view_mode)) {
      EntityViewMode::create([
        'id' => 'entity_test.' . $view_mode,
        'label' => $label,
        'targetEntityType' => 'entity_test',
      ])->save();
    }

    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => $view_mode,
    ])
      ->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->save();
  }

  /**
   * Builds the local task derivative definitions under test.
   *
   * @return array
   *   The derivative definitions, keyed by route name.
   */
  private function getDerivatives(): array {
    /** @var \Drupal\display_builder_entity_view\Plugin\Derivative\EntityOverrideViewLocalTask $deriver */
    $deriver = EntityOverrideViewLocalTask::create($this->container, 'display_builder_entity_view.display_builder_tabs');

    return $deriver->getDerivativeDefinitions([]);
  }

}
