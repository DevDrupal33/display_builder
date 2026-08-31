<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the Views Display Extender.
 *
 * @internal
 */
#[CoversClass(DisplayExtender::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
#[RunTestsInSeparateProcesses]
final class DisplayExtenderTest extends KernelTestBase {

  /**
   * The instance ID prefix of the buildable under test.
   *
   * @see \Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay
   */
  private const PREFIX = 'views__';

  /**
   * The display buildable manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'views_ui',
    'display_builder',
    'display_builder_views',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('view');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'views', 'display_builder', 'display_builder_views', 'ui_patterns']);
    $this->displayBuildableManager = $this->container->get('plugin.manager.display_buildable');
  }

  /**
   * Test the display extender form.
   */
  public function testExtenderInstantiationAndOptionsForm(): void {
    // Create a minimal view entity.
    $view = View::create([
      'id' => 'test_view',
      'label' => 'Test View',
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ]);
    $view->save();

    // Get the display object.
    $viewExecutable = Views::executableFactory()->get($view);
    $display = $viewExecutable->getDisplay();

    // Instantiate the extender plugin.
    $container = \Drupal::getContainer();
    $plugin = DisplayExtender::create(
      $container,
      [],
      'display_builder',
      [
        'id' => 'display_builder',
        'title' => 'Display Builder',
        'help' => 'Use display builder as output for this view.',
      ]
    );
    $plugin->init($viewExecutable, $display);

    // Test buildOptionsForm.
    $form = ['#title' => 'Test Form'];
    $form_state = new FormState();
    $form_state->set('section', 'display_builder');
    $plugin->buildOptionsForm($form, $form_state);
    self::assertArrayHasKey(DisplayBuildableInterface::PROFILE_PROPERTY, $form);

    // Test submitOptionsForm.
    $form_state->setValue(DisplayBuildableInterface::PROFILE_PROPERTY, 'default');
    $plugin->submitOptionsForm($form, $form_state);
    self::assertSame('default', $plugin->options[DisplayBuildableInterface::PROFILE_PROPERTY]);

    // Test optionsSummary.
    $categories = [];
    $options = [];
    $plugin->optionsSummary($categories, $options);
    self::assertArrayHasKey('display_builder', $options);

    // Test getInstanceId.
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('view_display', ['extender' => $plugin]);
    $instance_id = $buildable->getInstanceId();
    self::assertStringStartsWith(self::PREFIX, $instance_id);

    // Test static checkInstanceId and getUrlFromInstanceId.
    $id = \sprintf('%stest_view__default', self::PREFIX);
    $parsed = ViewDisplay::checkInstanceId($id);
    self::assertSame(['view' => 'test_view', 'display' => 'default'], $parsed);

    $url = ViewDisplay::getUrlFromInstanceId($id);
    self::assertStringContainsString('/admin/structure/views/view/test_view/display-builder/default', $url->toString());
  }

  /**
   * Test the ::getDisplayLabel method.
   */
  public function testGetDisplayLabel(): void {
    $view = View::create([
      'id' => 'frontpage_test',
      'label' => 'Frontpage',
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_title' => 'Page',
          'position' => 1,
          'display_options' => [],
        ],
      ],
    ]);
    $view->save();

    $viewExecutable = Views::executableFactory()->get($view);
    $viewExecutable->setDisplay('page_1');

    $plugin = DisplayExtender::create(
      \Drupal::getContainer(),
      [],
      'display_builder',
      [
        'id' => 'display_builder',
        'title' => 'Display Builder',
        'help' => 'Use display builder as output for this view.',
      ]
    );
    $plugin->init($viewExecutable, $viewExecutable->getDisplay());

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('view_display', ['extender' => $plugin]);

    self::assertSame('Views', $buildable->label());
    self::assertSame('Frontpage (Page)', $buildable->getDisplayLabel());
  }

  /**
   * Test the plugin is constructible for a display with no extender.
   *
   * The extender is absent whenever Display Builder is not registered as a
   * views display extender. Constructing must not fatal there, so that the
   * tolerant callers (::getDisplayLabel(), Instance::label()) can answer
   * "no name" instead of taking the page down.
   */
  public function testConstructWithoutExtender(): void {
    $view = View::create([
      'id' => 'no_extender',
      'label' => 'No Extender',
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ]);
    $view->save();

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('view_display', [
      'view_id' => 'no_extender',
      'view_display' => 'default',
    ]);

    self::assertSame('Views', $buildable->label());
    self::assertNull($buildable->getDisplayLabel());
  }

  /**
   * Test the set, switch and disable profile lifecycle on a view display.
   *
   * Deterministic, ajax-free counterpart to the Views UI profile lifecycle
   * that was previously covered end-to-end in Playwright (views.spec.ts):
   * selecting a profile attaches a Display Builder instance to the view
   * display, switching keeps the same instance, and disabling deletes it.
   */
  public function testProfileLifecycle(): void {
    $view = View::create([
      'id' => 'test_lifecycle',
      'label' => 'Test Lifecycle',
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ]);
    $view->save();

    $viewExecutable = Views::executableFactory()->get($view);
    $display = $viewExecutable->getDisplay();

    $plugin = DisplayExtender::create(
      \Drupal::getContainer(),
      [],
      'display_builder',
      [
        'id' => 'display_builder',
        'title' => 'Display Builder',
        'help' => 'Use display builder as output for this view.',
      ]
    );
    $plugin->init($viewExecutable, $display);

    $instance_storage = $this->container->get('entity_type.manager')->getStorage('display_builder_instance');
    $instance_id = \sprintf('%stest_lifecycle__default', self::PREFIX);

    $form = ['#title' => 'Test Form'];
    $form_state = new FormState();
    $form_state->set('section', 'display_builder');

    // No profile selected yet: no instance attached.
    self::assertNull($instance_storage->load($instance_id));

    // Set: selecting a profile attaches an instance to the display.
    $form_state->setValue(DisplayBuildableInterface::PROFILE_PROPERTY, 'test_base');
    $plugin->submitOptionsForm($form, $form_state);
    self::assertSame('test_base', $plugin->options[DisplayBuildableInterface::PROFILE_PROPERTY]);
    self::assertNotNull($instance_storage->load($instance_id));

    // Switch: changing the profile keeps the same instance.
    $form_state->setValue(DisplayBuildableInterface::PROFILE_PROPERTY, 'test_builder');
    $plugin->submitOptionsForm($form, $form_state);
    self::assertSame('test_builder', $plugin->options[DisplayBuildableInterface::PROFILE_PROPERTY]);
    self::assertNotNull($instance_storage->load($instance_id));

    // Disable: clearing the profile deletes the instance.
    $form_state->setValue(DisplayBuildableInterface::PROFILE_PROPERTY, '');
    $plugin->submitOptionsForm($form, $form_state);
    $instance_storage->resetCache([$instance_id]);
    self::assertNull($instance_storage->load($instance_id));
  }

  /**
   * Test the ::previewWithChrome() method.
   *
   * A page display registers its own route - visited for real, it appears
   * inside a page. A block display never does; it only ever renders embedded
   * in something else, so its preview must not be wrapped in chrome either.
   */
  #[DataProvider('providerTestPreviewWithChrome')]
  public function testPreviewWithChrome(bool $expected, string $display_plugin, string $display_id): void {
    $view = View::create([
      'id' => 'test_chrome_view',
      'label' => 'Test Chrome View',
      'base_table' => 'user',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Master',
          'position' => 0,
          'display_options' => [],
        ],
        $display_id => [
          'display_plugin' => $display_plugin,
          'id' => $display_id,
          'display_title' => 'Test display',
          'position' => 1,
          'display_options' => [],
        ],
      ],
    ]);
    $view->save();

    $viewExecutable = Views::executableFactory()->get($view);
    $viewExecutable->setDisplay($display_id);

    $plugin = DisplayExtender::create(
      \Drupal::getContainer(),
      [],
      'display_builder',
      [
        'id' => 'display_builder',
        'title' => 'Display Builder',
        'help' => 'Use display builder as output for this view.',
      ]
    );
    $plugin->init($viewExecutable, $viewExecutable->getDisplay());

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('view_display', ['extender' => $plugin]);

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
      'page display gets chrome' => [TRUE, 'page', 'page_1'],
      'block display is bare' => [FALSE, 'block', 'block_1'],
    ];
  }

}
