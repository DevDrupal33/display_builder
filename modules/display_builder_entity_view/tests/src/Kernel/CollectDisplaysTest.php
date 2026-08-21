<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityView;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the read-only display listing behind the Instances panel.
 *
 * Three things matter here, in this order: it lists displays that are not
 * built with Display Builder (the link in the chain users get stuck on), it
 * writes nothing (listing used to create and save instance entities), and the
 * override collection is bounded (it used to load every overridden node).
 *
 * @internal
 */
#[CoversClass(EntityView::class)]
#[CoversClass(EntityViewOverride::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class CollectDisplaysTest extends EntityKernelTestBase {

  /**
   * The name of the field storing an override.
   */
  private const OVERRIDE_FIELD = 'field_db_override';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_ui',
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

    foreach (['teaser' => 'Teaser', 'full' => 'Full', '_custom' => 'Custom'] as $mode => $label) {
      EntityViewMode::create([
        'id' => 'entity_test.' . $mode,
        'label' => $label,
        'targetEntityType' => 'entity_test',
      ])->save();
    }
  }

  /**
   * A display with no profile is listed, marked, and linked to Manage display.
   *
   * This is the gap that made the panel fail at the exact moment it was
   * needed: a landing page renders Article - Teaser, and if that teaser is
   * still a plain formatter display the panel used to show nothing at all.
   */
  public function testListsDisplaysNotBuiltWithDisplayBuilder(): void {
    $this->createDisplay('teaser');
    $this->createDisplay('default', 'test_base');

    $references = $this->buildable('entity_view')->collectDisplays();
    $by_label = $this->keyByLabel($references);

    self::assertArrayHasKey('Entity Test Bundle (Teaser)', $by_label);
    self::assertFalse($by_label['Entity Test Bundle (Teaser)']->built);
    // Not being built is an action, not a status: ::built is what says so, and
    // the panel renders a "Build" link rather than a status word.
    self::assertNull($by_label['Entity Test Bundle (Teaser)']->status());
    self::assertStringContainsString(
      '/display/teaser',
      $by_label['Entity Test Bundle (Teaser)']->url->toString(),
    );

    self::assertArrayHasKey('Entity Test Bundle (Default)', $by_label);
    self::assertTrue($by_label['Entity Test Bundle (Default)']->built);
    // Built but with no sources yet.
    self::assertSame('empty', $by_label['Entity Test Bundle (Default)']->status());
    self::assertStringContainsString(
      'display-builder',
      $by_label['Entity Test Bundle (Default)']->url->toString(),
    );
  }

  /**
   * Disabled displays and the '_custom' mode are not page levels.
   */
  public function testSkipsDisabledAndInternalDisplays(): void {
    $this->createDisplay('default');
    $disabled = $this->createDisplay('teaser');
    $disabled->setStatus(FALSE)->save();
    $this->createDisplay('_custom');

    $labels = \array_keys($this->keyByLabel($this->buildable('entity_view')->collectDisplays()));

    self::assertSame(['Entity Test Bundle (Default)'], $labels);
  }

  /**
   * Listing creates and saves nothing.
   *
   * Rendering a read-only navigation panel used to write instance rows nobody
   * asked for, on every build. This is the assertion that keeps it read-only.
   */
  public function testListingHasNoSideEffects(): void {
    $this->createDisplay('default', 'test_base');
    $this->createDisplay('teaser');
    $this->createOverrideSetup();

    $storage = $this->container->get('entity_type.manager')->getStorage('display_builder_instance');
    $before = \count($storage->loadMultiple());

    $this->buildable('entity_view')->collectDisplays();
    $this->buildable('entity_view_override')->collectDisplays();

    $storage->resetCache();
    self::assertSame($before, \count($storage->loadMultiple()));
  }

  /**
   * The override collection stops at the limit instead of loading everything.
   */
  public function testOverrideCollectionIsBounded(): void {
    $this->createOverrideSetup();

    for ($i = 0; $i < 7; ++$i) {
      EntityTest::create([
        'type' => 'entity_test',
        'name' => 'Overridden ' . $i,
        self::OVERRIDE_FIELD => [['source_id' => 'textfield', 'source' => ['value' => 'x']]],
      ])->save();
    }

    self::assertCount(7, $this->buildable('entity_view_override')->collectDisplays());
    self::assertCount(3, $this->buildable('entity_view_override')->collectDisplays(['limit' => 3]));
  }

  /**
   * A buildable plugin built with no configuration, as a listing needs it.
   *
   * @param string $plugin_id
   *   The buildable plugin ID.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The plugin.
   */
  private function buildable(string $plugin_id): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance($plugin_id, []);

    return $buildable;
  }

  /**
   * Key references by their display label.
   *
   * @param \Drupal\display_builder\DisplayReference[] $references
   *   The references.
   *
   * @return array<string, \Drupal\display_builder\DisplayReference>
   *   Keyed references.
   */
  private function keyByLabel(array $references): array {
    $keyed = [];

    foreach ($references as $reference) {
      $keyed[$reference->label] = $reference;
    }

    return $keyed;
  }

  /**
   * Create an entity view display.
   *
   * @param string $view_mode
   *   The view mode.
   * @param string|null $profile_id
   *   A Display Builder profile, or NULL for a plain core display.
   *
   * @return \Drupal\display_builder_entity_view\Entity\EntityViewDisplay
   *   The saved display.
   */
  private function createDisplay(string $view_mode, ?string $profile_id = NULL): EntityViewDisplay {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => $view_mode,
    ]);
    $display->setStatus(TRUE);

    if ($profile_id !== NULL) {
      $display->setThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY, $profile_id);
    }
    $display->save();

    return $display;
  }

  /**
   * Create the field and display an override needs to exist.
   */
  private function createOverrideSetup(): void {
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
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->save();
  }

}
