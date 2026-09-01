<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    [$entity, $buildable] = $this->createOverrideFixture('full');

    self::assertSame('Entity view override', $buildable->label());
    self::assertSame(
      \sprintf('Entity Test Bundle [%s] (Full)', $entity->id()),
      $buildable->getDisplayLabel(),
    );
  }

  /**
   * Test the ::previewWithChrome() method.
   *
   * Delegates to the same entity-type/view-mode check as ::EntityView,
   * exercised here through the override plugin to prove the wiring, not the
   * rule itself.
   *
   * @see \Drupal\Tests\display_builder_entity_view\Kernel\EntityViewDisplayTest::testPreviewWithChrome()
   */
  #[DataProvider('providerTestPreviewWithChrome')]
  public function testPreviewWithChrome(bool $expected, string $view_mode): void {
    \Drupal::service('router.builder')->rebuild();

    [, $buildable] = $this->createOverrideFixture($view_mode);

    self::assertSame($expected, $buildable->previewWithChrome());
  }

  /**
   * Provides test data for ::testPreviewWithChrome().
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestPreviewWithChrome(): array {
    return [
      'full view mode gets chrome' => [TRUE, 'full'],
      'teaser view mode is bare' => [FALSE, 'teaser'],
    ];
  }

  /**
   * Reproduces #3613399: Revert then Restore must not empty the display.
   *
   * Revert legitimately clears the override field and falls back to the
   * display's default sources. Restore must not then overwrite that default
   * with the now-empty "published" data - there is nothing published left to
   * restore to, so it must no-op instead of blanking the display.
   */
  public function testRestoreAfterRevertDoesNotEmptyDisplay(): void {
    $defaultSources = [
      ['node_id' => 'default', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []],
    ];
    [, $buildable] = $this->createOverrideFixture('full', $defaultSources);
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();

    $overrideData = [
      ['node_id' => '1', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []],
    ];
    $instance->setNewPresent($overrideData, 'Initial override');
    $instance->publish();
    self::assertTrue($instance->isPublished());
    self::assertTrue($instance->isPublishedPresent());

    $instance->revert();
    self::assertFalse($instance->isPublished());
    $revertedState = $instance->getCurrentState();
    self::assertNotSame([], $revertedState);

    $instance->restore();
    self::assertSame($revertedState, $instance->getCurrentState());
  }

  /**
   * Verifies a new override starts from the default display, not blank.
   *
   * ::getInitialSources() copies the underlying entity_view buildable's
   * sources on first creation, so an editor overriding a display that was
   * itself built with Display Builder sees that arrangement already in
   * place rather than an empty canvas.
   */
  public function testOverrideStartsFromDefaultDisplay(): void {
    $defaultSources = [
      ['node_id' => 'default', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []],
    ];
    [, $buildable] = $this->createOverrideFixture('full', $defaultSources, 'test_base');
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();

    self::assertSame($defaultSources, $instance->getCurrentState());
  }

  /**
   * Verifies the other half: no default profile, no sources to copy.
   *
   * The copy step is gated on the underlying display carrying a Display
   * Builder profile. A display Display Builder never built has nothing to
   * copy, so the override legitimately starts blank rather than pulling in
   * unrelated third-party setting data.
   */
  public function testOverrideStartsBlankWithoutDefaultProfile(): void {
    $defaultSources = [
      ['node_id' => 'default', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []],
    ];
    [, $buildable] = $this->createOverrideFixture('full', $defaultSources);
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();

    self::assertSame([], $instance->getCurrentState());
  }

  /**
   * Verifies changing the default display later does not affect an override.
   *
   * The default display's sources are only ever copied once, into a new
   * override's own instance and field data (see the "starts from the
   * default display" test above). Neither ::EntityViewOverride::getSources()
   * nor the persisted instance ever read the default display's third-party
   * settings again afterward, so changing what the default display looks
   * like later - even to something structurally unrelated - must not reach
   * an override that already has its own instance.
   */
  public function testOverrideIsUnaffectedByLaterDefaultDisplayChanges(): void {
    $originalDefault = [
      ['node_id' => 'default', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []],
    ];
    [$entity, $buildable, $display] = $this->createOverrideFixture('full', $originalDefault, 'test_base');
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();
    self::assertSame($originalDefault, $instance->getCurrentState());
    $instance->publish();

    // The default display changes later, to a structurally unrelated
    // arrangement.
    $changedDefault = [
      ['node_id' => 'changed', 'source_id' => 'other_component', 'source' => [], 'third_party_settings' => []],
    ];
    $display->setThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, $changedDefault)->save();

    // A fresh buildable and instance, built from a freshly loaded entity as a
    // new request would, must still see the override's own published data,
    // unaffected by the default display change: reusing the in-memory
    // $buildable here would only prove PHP object caching, not persistence.
    $reloadedEntity = \Drupal::entityTypeManager()->getStorage('entity_test')->loadUnchanged($entity->id());
    /** @var \Drupal\display_builder\DisplayBuildableInterface $reloadedBuildable */
    $reloadedBuildable = $this->displayBuildableManager->createInstance('entity_view_override', [
      'display' => $display,
      'entity' => $reloadedEntity,
    ]);
    self::assertSame($originalDefault, $reloadedBuildable->getSources());

    $reloadedBuildable->initInstanceIfMissing();
    self::assertSame($originalDefault, $reloadedBuildable->getInstance()->getCurrentState());
  }

  /**
   * Verifies an override's data travels with the host entity's revisions.
   *
   * ::EntityViewOverride::saveSources() starts a new host-entity revision on
   * publish, when the entity type is revisionable, so the override field is
   * a normal revisionable field like any other. Loading an older entity
   * revision must return that revision's own override data, not always
   * whatever is current.
   */
  public function testOverrideTravelsWithEntityRevisions(): void {
    $this->installEntitySchema('entity_test_rev');

    EntityViewMode::create([
      'id' => 'entity_test_rev.full',
      'label' => 'Full',
      'targetEntityType' => 'entity_test_rev',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => self::OVERRIDE_FIELD,
      'entity_type' => 'entity_test_rev',
      'type' => 'ui_patterns_source',
    ])->save();
    FieldConfig::create([
      'field_name' => self::OVERRIDE_FIELD,
      'entity_type' => 'entity_test_rev',
      'bundle' => 'entity_test_rev',
      'label' => 'Display Builder override',
    ])->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test_rev',
      'bundle' => 'entity_test_rev',
      'mode' => 'full',
    ]);
    $display
      ->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->save();

    $entity = EntityTestRev::create(['type' => 'entity_test_rev', 'name' => 'Revisionable test entity']);
    $entity->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('entity_view_override', [
      'display' => $display,
      'entity' => $entity,
    ]);
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();

    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_rev');

    $sourcesA = [['node_id' => 'a', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []]];
    $instance->setNewPresent($sourcesA, 'Version A');
    $instance->publish();
    $revisionIdA = $storage->loadUnchanged($entity->id())->getRevisionId();

    $sourcesB = [['node_id' => 'b', 'source_id' => 'other_component', 'source' => [], 'third_party_settings' => []]];
    $instance->setNewPresent($sourcesB, 'Version B');
    $instance->publish();
    $entityAfterB = $storage->loadUnchanged($entity->id());
    self::assertNotSame($revisionIdA, $entityAfterB->getRevisionId());
    self::assertSame($sourcesB, $entityAfterB->get(self::OVERRIDE_FIELD)->getValue());

    // The earlier revision must still show version A, not the now-current
    // version B: the override rides along with the revision it was
    // published into, rather than always reflecting the latest.
    $revisionA = $storage->loadRevision($revisionIdA);
    self::assertSame($sourcesA, $revisionA->get(self::OVERRIDE_FIELD)->getValue());
  }

  /**
   * Creates an entity, its override display and the buildable built from it.
   *
   * @param string $view_mode
   *   The view mode machine name.
   * @param array $default_sources
   *   Sources third-party setting on the display, standing in for the
   *   display's default content. Empty by default, matching displays with
   *   no default content configured.
   * @param string|null $default_profile_id
   *   Profile third-party setting on the display, standing in for the
   *   default display having been built with Display Builder. NULL by
   *   default, matching a display Display Builder never touched.
   *
   * @return array{0: \Drupal\entity_test\Entity\EntityTest, 1: \Drupal\display_builder\DisplayBuildableInterface, 2: \Drupal\display_builder_entity_view\Entity\EntityViewDisplay}
   *   The entity, the buildable wrapping it, and the display itself.
   */
  private function createOverrideFixture(string $view_mode, array $default_sources = [], ?string $default_profile_id = NULL): array {
    EntityViewMode::create([
      'id' => 'entity_test.' . $view_mode,
      'label' => \ucfirst($view_mode),
      'targetEntityType' => 'entity_test',
    ])->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => $view_mode,
    ]);
    $display
      ->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->setThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, $default_sources);

    if ($default_profile_id !== NULL) {
      $display->setThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY, $default_profile_id);
    }
    $display->save();

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

    return [$entity, $buildable, $display];
  }

}
