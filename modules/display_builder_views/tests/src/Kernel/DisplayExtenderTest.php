<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Views;
use Drupal\views\Entity\View;
use Drupal\display_builder\ConfigFormBuilderInterface;

/**
 * Kernel test for the Views Display Extender.
 *
 * @internal
 */
final class DisplayExtenderTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'views',
    'views_ui',
    'display_builder',
    'display_builder_views',
    'ui_patterns',
  ];

  /**
   *
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('view');
    $this->installEntitySchema('display_builder');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'views', 'display_builder', 'display_builder_views', 'ui_patterns']);
  }

  /**
   *
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
    $this->assertArrayHasKey(ConfigFormBuilderInterface::PROFILE_PROPERTY, $form);

    // Test submitOptionsForm.
    $form_state->setValue(ConfigFormBuilderInterface::PROFILE_PROPERTY, 'default');
    $plugin->submitOptionsForm($form, $form_state);
    $this->assertEquals('default', $plugin->options[ConfigFormBuilderInterface::PROFILE_PROPERTY]);

    // Test optionsSummary.
    $categories = [];
    $options = [];
    $plugin->optionsSummary($categories, $options);
    $this->assertArrayHasKey('display_builder', $options);

    // Test getInstanceId.
    $instance_id = $plugin->getInstanceId();
    $this->assertStringStartsWith('view__', $instance_id);

    // Test static checkInstanceId and getUrlFromInstanceId.
    $parsed = DisplayExtender::checkInstanceId('view__test_view__default');
    $this->assertEquals(['view' => 'test_view', 'display' => 'default'], $parsed);

    $url = DisplayExtender::getUrlFromInstanceId('view__test_view__default');
    $this->assertStringContainsString('/admin/structure/views/view/test_view/display-builder/default', $url->toString());
  }

}
