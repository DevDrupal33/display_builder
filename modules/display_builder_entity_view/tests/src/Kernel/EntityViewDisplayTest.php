<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplayTrait;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the Display Builder EntityViewDisplay and EntityViewDisplayTrait.
 *
 * @internal
 */
#[CoversClass(EntityViewDisplay::class)]
#[CoversClass(EntityViewDisplayTrait::class)]
#[Group('display_builder')]
final class EntityViewDisplayTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'display_builder',
    'display_builder_entity_view',
    'display_builder_test',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_field_formatters',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')
      ->install([
        'display_builder_theme_test',
      ]);
    $this->config('system.theme')->set('default', 'display_builder_theme_test')->save();

    $this->installConfig(['display_builder']);
  }

  /**
   * Test the ::isDisplayBuilderEnabled method.
   *
   * @param bool $expected
   *   The expected result.
   * @param string $view_mode
   *   The view mode to test.
   */
  #[DataProvider('providerTestIsDisplayBuilderEnabled')]
  public function testIsDisplayBuilderEnabled($expected, $view_mode): void {
    if ($view_mode !== 'default') {
      EntityViewMode::create([
        'id' => 'entity_test.' . $view_mode,
        'label' => $view_mode,
        'targetEntityType' => 'entity_test',
      ])->save();
    }
    $display = self::createTestDisplay($view_mode);

    self::assertSame($view_mode, $display->getMode());
    $id = \sprintf('%sentity_test__entity_test__%s', EntityViewDisplay::getPrefix(), $view_mode);
    self::assertSame($id, $display->getInstanceId(), $view_mode);
    self::assertFalse($display->isDisplayBuilderEnabled());

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, 'test')->save();

    self::assertSame($expected, $display->isDisplayBuilderEnabled());
  }

  /**
   * Provides test data for ::testIsDisplayBuilderEnabled().
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestIsDisplayBuilderEnabled(): array {
    return [
      'default enabled' => [
        TRUE,
        'default',
      ],
      'teaser enabled' => [
        TRUE,
        'teaser',
      ],
      // Display Builder must not be enabled for the '_custom' view mode that
      // is used for on-the-fly rendering of fields in isolation from the
      // entity. @see isDisplayBuilderEnabled().
      'custom disabled' => [
        FALSE,
        '_custom',
      ],
    ];
  }

  /**
   * Test the ::calculateDependencies method.
   */
  public function testCalculateDependencies(): void {
    $sources = Yaml::decode(\file_get_contents(__DIR__ . '/../../fixtures/sources.yml'));

    $display = self::createTestDisplay();

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, 'test')->save();
    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, $sources)->save();
    $display->calculateDependencies();

    $changed = $display->onDependencyRemoval([
      'config' => [],
      'theme' => ['display_builder_theme_test'],
      'module' => [],
    ]);

    self::assertTrue($changed);
  }

  /**
   * Test the ::getBuilderUrl method.
   */
  public function testGetBuilderUrl(): void {
    $display = self::createTestDisplay();

    $url = $display->getBuilderUrl();

    self::assertSame('display_builder_entity_view.entity_test', $url->getRouteName());
    self::assertSame([
      'bundle' => 'entity_test',
      'view_mode_name' => 'default',
    ], $url->getRouteParameters());
  }

  /**
   * Test the ::getUrlFromInstanceId method.
   */
  public function testGetUrlFromInstanceId(): void {
    $display = self::createTestDisplay();

    $id = \sprintf('%sentity_test__entity_test__default', EntityViewDisplay::getPrefix());
    $url = $display::getUrlFromInstanceId($id);

    self::assertSame('display_builder_entity_view.entity_test', $url->getRouteName());
    self::assertSame([
      'bundle' => 'entity_test',
      'view_mode_name' => 'default',
      'entity' => 'entity_test',
    ], $url->getRouteParameters());
  }

  /**
   * Test the ::getDisplayBuilderOverrideField method.
   */
  public function testGetDisplayBuilderOverrideField(): void {
    $display = self::createTestDisplay();

    $field = $display->getDisplayBuilderOverrideField();
    self::assertNull($field);

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY, 'foo')->save();

    $field = $display->getDisplayBuilderOverrideField();
    self::assertSame('foo', $field);
  }

  /**
   * Test the ::getDisplayBuilderOverrideProfile method.
   */
  public function testGetDisplayBuilderOverrideProfile(): void {
    $display = self::createTestDisplay();

    $profile = $display->getDisplayBuilderOverrideProfile();
    self::assertNull($profile);

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY, 'test')->save();

    $profile = $display->getDisplayBuilderOverrideProfile();
    self::assertSame('test', $profile->id());
  }

  /**
   * Test the ::getDisplayBuilder method.
   */
  public function testGetDisplayBuilder(): void {
    $display = self::createTestDisplay();

    $profile = $display->getProfile();
    self::assertNull($profile);

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, 'test')->save();

    $profile = $display->getProfile();
    self::assertSame('test', $profile->id());
  }

  /**
   * Test the ::isDisplayBuilderOverridable method.
   */
  public function testIsDisplayBuilderOverridable(): void {
    $display = self::createTestDisplay();

    self::assertFalse($display->isDisplayBuilderOverridable());

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY, 'foo')->save();

    self::assertFalse($display->isDisplayBuilderOverridable());

    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY, 'test')->save();

    self::assertTrue($display->isDisplayBuilderOverridable());
  }

  /**
   * Test the ::getSources method.
   */
  public function testGetSources(): void {
    $display = self::createTestDisplay();

    $sources = $display->getSources();
    self::assertEmpty($sources);

    $expected = Yaml::decode(\file_get_contents(__DIR__ . '/../../fixtures/sources.yml'));
    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, $expected)->save();

    $sources = $display->getSources();
    self::assertSame($expected, $sources);
  }

  /**
   * Test the ::saveSources method.
   */
  public function testSaveSources(): void {
    $display = self::createTestDisplayWithProfile();

    $sources = $display->getSources();
    self::assertEmpty($sources);

    $expected = Yaml::decode(\file_get_contents(__DIR__ . '/../../fixtures/sources.yml'));

    // Create the instance and fill sources.
    $display->initInstanceIfMissing();

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($display->getInstanceId());
    $instance->setNewPresent($expected);
    $instance->save();

    $display->saveSources();
    $sources = $display->getSources();
    self::removeNodeId($sources);

    self::assertSame($expected, $sources);
  }

  /**
   * Test the ::initInstanceIfMissing method.
   */
  public function testInitInstanceIfMissing(): void {
    $display = self::createTestDisplayWithProfile();

    $display->initInstanceIfMissing();
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($display->getInstanceId());

    self::assertNotNull($instance);
    $id = \sprintf('%sentity_test__entity_test__default', EntityViewDisplay::getPrefix());
    self::assertSame($id, $instance->id());
  }

  /**
   * Helper to create and save a test display.
   *
   * @param string $view_mode
   *   (Optional) The view mode. Default 'default'.
   * @param string $entity
   *   (Optional) The entity type. Default 'entity_test'.
   * @param string $bundle
   *   (Optional) The bundle. Default 'entity_test'.
   *
   * @return \Drupal\display_builder_entity_view\Entity\EntityViewDisplay
   *   The created display.
   */
  private static function createTestDisplay(string $view_mode = 'default', string $entity = 'entity_test', string $bundle = 'entity_test'): EntityViewDisplay {
    $display = EntityViewDisplay::create([
      'targetEntityType' => $entity,
      'bundle' => $bundle,
      'mode' => $view_mode,
    ]);
    $display->setStatus(TRUE)->save();

    return $display;
  }

  /**
   * Helper to create and save a test display with profile.
   *
   * @param string $profile_id
   *   (Optional) The profile id. Default 'test'.
   *
   * @return \Drupal\display_builder_entity_view\Entity\EntityViewDisplay
   *   The created display.
   */
  private static function createTestDisplayWithProfile(string $profile_id = 'test'): EntityViewDisplay {
    $display = self::createTestDisplay();
    $display->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, $profile_id)->save();

    return $display;
  }

  /**
   * Recursively remove the _node_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function removeNodeId(array &$array): void {
    unset($array['node_id']);

    foreach ($array as $key => &$value) {
      if (\is_array($value)) {
        self::removeNodeId($value);

        if (isset($value['source_id'], $value['source']['value']) && empty($value['source']['value'])) {
          unset($array[$key]);
        }
      }

      if ($value instanceof MarkupInterface) {
        $array[$key] = (string) $value;
      }
    }
  }

}
