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
    self::assertStringStartsWith(ViewDisplay::getPrefix(), $instance_id);

    // Test static checkInstanceId and getUrlFromInstanceId.
    $id = \sprintf('%stest_view__default', ViewDisplay::getPrefix());
    $parsed = ViewDisplay::checkInstanceId($id);
    self::assertSame(['view' => 'test_view', 'display' => 'default'], $parsed);

    $url = ViewDisplay::getUrlFromInstanceId($id);
    self::assertStringContainsString('/admin/structure/views/view/test_view/display-builder/default', $url->toString());
  }

}
