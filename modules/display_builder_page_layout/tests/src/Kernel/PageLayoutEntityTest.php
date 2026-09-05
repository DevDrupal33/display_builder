<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Routing\RouteMatch;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_page_layout\EventSubscriber\PageVariantSubscriber;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

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
   * The instance ID prefix of the buildable under test.
   *
   * @see \Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout
   */
  private const PREFIX = 'page_layout__';

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
    $this->installConfig(['display_builder', 'display_builder_test']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->displayBuildableManager = $this->container->get('plugin.manager.display_buildable');
  }

  /**
   * Test the ::getDisplayLabel method.
   *
   * A page layout has no parent to disambiguate it, so there is no
   * '<specific> (<parent>)' pair here, only the entity label.
   */
  public function testGetDisplayLabel(): void {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'test_label',
      'label' => 'Test Layout',
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);

    self::assertSame('Page layout', $buildable->label());
    self::assertSame('Test Layout', $buildable->getDisplayLabel());
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
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $buildable->initInstanceIfMissing();

    $instance = $buildable->getInstance();
    self::assertNotNull($instance, 'PageLayout instance loaded.');

    // Load the entity.
    $loaded = PageLayout::load('test_layout');
    self::assertNotNull($loaded, 'PageLayout entity loaded.');
    self::assertSame('Test Layout', $loaded->label());
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $loaded]);

    // Test getInstanceId().
    $id = self::PREFIX . 'test_layout';
    self::assertSame($id, $buildable->getInstanceId());

    $instance = $buildable->getInstance();
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
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
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
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
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
    $actual = $instance->getSources();
    self::removeNodeId($actual);

    self::assertSame($expected, $actual);
  }

  /**
   * Test the ::previewWithChrome() method.
   *
   * A page layout draws its own header and footer; wrapping its preview in
   * another page would show both twice.
   */
  public function testPreviewWithChromeIsAlwaysFalse(): void {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'test_chrome',
      'label' => 'Test Chrome',
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);

    self::assertFalse($buildable->previewWithChrome());
  }

  /**
   * Verifies disabling a page layout returns the theme's own regions.
   *
   * ::AccessControlHandler::checkAccess() denies 'view' access to a disabled
   * page layout, so ::PageVariantSubscriber finds nothing to select and
   * leaves the page display variant plugin untouched - letting core's
   * default (Block Layout, the theme's own regions) stand rather than
   * forcing `display_builder_page_layout`.
   */
  public function testDisablingReturnsThemeRegions(): void {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'test_disable',
      'label' => 'Test Disable',
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
      DisplayBuildableInterface::SOURCES_PROPERTY => [
        ['source_id' => 'component', 'source' => [], 'third_party_settings' => []],
      ],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();

    $subscriber = new PageVariantSubscriber($this->entityTypeManager);
    $routeMatch = new RouteMatch('test.route', new Route('/test-path', [], [], ['_admin_route' => FALSE]));

    $event = new PageDisplayVariantSelectionEvent('block_page', $routeMatch);
    $subscriber->onSelectPageDisplayVariant($event);
    self::assertSame('display_builder_page_layout', $event->getPluginId());

    $entity->setStatus(FALSE)->save();
    // A real disable and a real subsequent pageview are two separate HTTP
    // requests, each with its own access control handler. Reset the
    // handler's static per-request access cache to match that, rather than
    // exercising the same-request staleness Drupal's access system is known
    // to have (see EntityAccessControlHandler::$accessCache).
    $this->entityTypeManager->getAccessControlHandler('page_layout')->resetCache();

    $event = new PageDisplayVariantSelectionEvent('block_page', $routeMatch);
    $subscriber->onSelectPageDisplayVariant($event);
    self::assertSame('block_page', $event->getPluginId());
  }

  /**
   * Verifies building a layout while disabled, then enabling it, works.
   *
   * Only the 'view' operation is status-gated
   * (::AccessControlHandler::checkAccess()); editing and publishing content
   * go through the entity's own save, untouched by status. So a page layout
   * can be built out fully while disabled - kept off visitors, as the test
   * above confirms - and enabling it afterward is what makes it live, with
   * none of the content built while disabled lost or reset.
   */
  public function testBuildingWhileDisabledThenEnabling(): void {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'test_build_disabled',
      'label' => 'Test Build Disabled',
      DisplayBuildableInterface::PROFILE_PROPERTY => 'test_base',
      DisplayBuildableInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(FALSE)->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $buildable->initInstanceIfMissing();
    $instance = $buildable->getInstance();

    $built = [
      ['source_id' => 'component', 'source' => [], 'third_party_settings' => []],
    ];
    $instance->setNewPresent($built, 'Draft build');
    $instance->publish();

    // ::publish() saves through the Instance's own resolved buildable
    // plugin, a separate PHP object loaded fresh from storage - not the
    // $entity handle above. Reload before mutating it further, or this
    // would save $entity's stale empty sources right back over what
    // ::publish() just wrote.
    $entity = PageLayout::load('test_build_disabled');

    $subscriber = new PageVariantSubscriber($this->entityTypeManager);
    $routeMatch = new RouteMatch('test.route', new Route('/test-path', [], [], ['_admin_route' => FALSE]));

    // Still not exposed to visitors while disabled, even with content built.
    $event = new PageDisplayVariantSelectionEvent('block_page', $routeMatch);
    $subscriber->onSelectPageDisplayVariant($event);
    self::assertSame('block_page', $event->getPluginId());

    $entity->setStatus(TRUE)->save();
    $this->entityTypeManager->getAccessControlHandler('page_layout')->resetCache();

    // Enabling makes it live, and the content built while disabled is intact.
    $event = new PageDisplayVariantSelectionEvent('block_page', $routeMatch);
    $subscriber->onSelectPageDisplayVariant($event);
    self::assertSame('display_builder_page_layout', $event->getPluginId());

    $actual = PageLayout::load('test_build_disabled')->getSources();
    self::removeNodeId($actual);
    self::assertSame($built, $actual);
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
