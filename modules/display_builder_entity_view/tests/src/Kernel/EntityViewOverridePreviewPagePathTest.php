<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel test for the page an entity view override can be previewed on.
 *
 * An override belongs to one entity, so the entity's canonical page is the
 * page it appears on - but only for the view mode that page actually renders.
 * Offering a teaser override the canonical page would preview the wrong
 * display while looking entirely convincing.
 *
 * @internal
 */
#[CoversClass(EntityViewOverride::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class EntityViewOverridePreviewPagePathTest extends EntityKernelTestBase {

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
   * A full-mode override previews on the entity's own page.
   */
  public function testFullModePreviewsOnTheEntityPage(): void {
    $entity = $this->createEntity();

    self::assertSame(
      '/entity_test/' . $entity->id(),
      $this->getPreviewPagePath('full', $entity),
    );
  }

  /**
   * A site in a subdirectory gets the same path as one at the root.
   *
   * The contract is a site path, and the caller resolves it from the site
   * root. A base path baked in here routes nowhere, and the preview quietly
   * falls back to rendering the sources on their own.
   */
  public function testPathCarriesNoBasePath(): void {
    $request = Request::create('http://localhost/subdir/index.php/entity_test/1', 'GET', [], [], [], [
      'SCRIPT_FILENAME' => '/var/www/subdir/index.php',
      'SCRIPT_NAME' => '/subdir/index.php',
      'PHP_SELF' => '/subdir/index.php/entity_test/1',
    ]);
    $stack = $this->container->get('request_stack');
    $request->setSession($stack->getCurrentRequest()->getSession());
    $stack->push($request);
    self::assertSame('/subdir', $request->getBasePath());

    // What Url::toString() measures from, and what kernel.request would set
    // on a real request.
    $this->container->get('router.request_context')->fromRequest($request);

    $entity = $this->createEntity();

    self::assertSame(
      '/entity_test/' . $entity->id(),
      $this->getPreviewPagePath('full', $entity),
    );
  }

  /**
   * Any other view mode has no page of its own.
   *
   * A teaser is shown inside some other page, and the canonical page renders
   * the full mode, so there is no page where this override is what shows.
   */
  public function testOtherViewModesHaveNoPage(): void {
    self::assertNull($this->getPreviewPagePath('teaser', $this->createEntity()));
  }

  /**
   * The default mode previews on the entity page when there is no full mode.
   *
   * A bundle with no distinct, enabled `full` display falls back to
   * `default` for its canonical page, so an override of `default` is what
   * that page actually shows.
   */
  public function testDefaultModePreviewsOnTheEntityPageWithoutFullMode(): void {
    $entity = $this->createEntity();

    self::assertSame(
      '/entity_test/' . $entity->id(),
      $this->getPreviewPagePath('default', $entity),
    );
  }

  /**
   * The default mode has no page of its own once a full mode exists.
   *
   * Once the bundle gets a distinct, enabled `full` display, the canonical
   * page renders that instead, so `default` stops being what it shows.
   */
  public function testDefaultModeHasNoPageOnceFullModeExists(): void {
    $entity = $this->createEntity();
    EntityViewMode::create([
      'id' => 'entity_test.full',
      'label' => 'Full',
      'targetEntityType' => 'entity_test',
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'full',
    ])->setStatus(TRUE)->save();

    self::assertNull($this->getPreviewPagePath('default', $entity));
  }

  /**
   * An unsaved entity has no page yet.
   */
  public function testUnsavedEntityHasNoPage(): void {
    $entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => 'Not saved',
    ]);

    self::assertNull($this->getPreviewPagePath('full', $entity));
  }

  /**
   * Creates a saved test entity.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The entity.
   */
  private function createEntity(): EntityTest {
    $entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => 'My test entity',
    ]);
    $entity->save();

    return $entity;
  }

  /**
   * Gets the page an override of this view mode would be previewed on.
   *
   * @param string $mode
   *   The view mode the override belongs to.
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The entity carrying the override.
   *
   * @return string|null
   *   The entity's own page, or NULL when there is none to preview on.
   */
  private function getPreviewPagePath(string $mode, EntityTest $entity): ?string {
    EntityViewMode::create([
      'id' => 'entity_test.' . $mode,
      'label' => \ucfirst($mode),
      'targetEntityType' => 'entity_test',
    ])->save();

    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => $mode,
    ]);
    $display
      ->setStatus(TRUE)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY, self::OVERRIDE_FIELD)
      ->setThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY, 'test_base')
      ->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance('entity_view_override', [
      'display' => $display,
      'entity' => $entity,
    ]);

    return $buildable->getPreviewPagePath();
  }

}
