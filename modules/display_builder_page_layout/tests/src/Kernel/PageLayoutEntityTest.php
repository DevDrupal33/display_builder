<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the PageLayout config entity and its form.
 *
 * @internal
 */
#[CoversClass(PageLayout::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class PageLayoutEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
    'user',
    'display_builder',
    'display_builder_test',
    'display_builder_page_layout',
    'ui_patterns',
    'ui_patterns_field',
    'path_alias',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The display buildable manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

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

    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->displayBuildableManager = $this->container->get('plugin.manager.display_buildable');
  }

  /**
   * Test basic CRUD operations for the PageLayout config entity.
   */
  public function testPageLayoutEntityCrud(): void {
    // Create a PageLayout entity.
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'test_layout',
      'label' => 'Test Layout',
      'weight' => 1,
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $buildable->initInstanceIfMissing();

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($buildable->getInstanceId());
    self::assertNotNull($instance, 'PageLayout instance loaded.');

    // Load the entity.
    $loaded = PageLayout::load('test_layout');
    self::assertNotNull($loaded, 'PageLayout entity loaded.');
    self::assertSame('Test Layout', $loaded->label());
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $loaded]);

    // Test getInstanceId().
    $id = \sprintf('%s%s', $buildable::getPrefix(), 'test_layout');
    self::assertSame($id, $buildable->getInstanceId());

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($buildable->getInstanceId());
    self::assertNotNull($instance);

    // Delete the entity.
    $entity->delete();
    self::assertNull(PageLayout::load('test_layout'), 'Entity deleted.');

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($buildable->getInstanceId());
    self::assertNull($instance);
  }

  /**
   * Test editing and updating a PageLayout config entity.
   */
  public function testPageLayoutEntityEdit(): void {
    // Create and save the entity.
    $entity = PageLayout::create([
      'id' => 'edit_layout',
      'label' => 'Original Label',
      'weight' => 5,
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $buildable->initInstanceIfMissing();

    // Load and edit the entity.
    $loaded = PageLayout::load('edit_layout');
    self::assertSame('Original Label', $loaded->label());

    // Change label and weight.
    $loaded->set('label', 'Updated Label');
    $loaded->set('weight', 10);
    $loaded->save();

    // Reload and assert changes.
    $updated = PageLayout::load('edit_layout');
    self::assertSame('Updated Label', $updated->label());
    self::assertSame(10, $updated->get('weight'));
  }

  /**
   * Test the config import updates the instance to use imported sources.
   */
  public function testConfigImportUpdatesInstance(): void {
    // Create a PageLayout entity.
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'edit_layout',
      'label' => 'Original Label',
      'weight' => 5,
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $buildable->initInstanceIfMissing();
    $expected = [
      [
        'source_id' => 'page_layout',
        'source' => [
          'regions' => [
            'header' => [
              [
                'source_id' => 'block',
                'source' => [
                  'plugin_id' => 'local_tasks_block',
                ],
              ],
            ],
          ],
        ],
        'third_party_settings' => [],
      ],
    ];
    $entity->setSyncing(TRUE);
    $entity->set(DisplayBuildableInterface::SOURCES_PROPERTY, $expected);
    $entity->save();
    $entity->setSyncing(FALSE);

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($buildable->getInstanceId());
    $actual = $instance->getCurrentState();
    self::removeNodeId($actual);

    self::assertSame($expected, $actual);
  }

  /**
   * Recursively remove the _node_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function removeNodeId(array &$array): void {
    unset($array['node_id']);

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::removeNodeId($value);
      }
    }
  }

}
