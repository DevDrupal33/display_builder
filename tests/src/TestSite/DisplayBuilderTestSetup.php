<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\TestSite;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\TestSite\TestSetupInterface;

/**
 * Setup file used by tests/src/Playwright/tests/.
 *
 * @see \Drupal\Tests\Scripts\TestSiteApplicationTest
 */
class DisplayBuilderTestSetup implements TestSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    // Install required modules.
    $module_installer = \Drupal::service('module_installer');
    \assert($module_installer instanceof ModuleInstallerInterface);
    $modules = [
      'display_builder',
      'display_builder_ui',
      'display_builder_test',
      // To have a valid instance to work with in tests.
      'display_builder_page_layout',
      'display_builder_page_layout_test',
      'ui_patterns',
      'ui_styles',
    ];
    $module_installer->install($modules);

    // Install DB test theme and set it as the default theme.
    $theme_installer = \Drupal::service('theme_installer');
    \assert($theme_installer instanceof ThemeInstallerInterface);
    $theme_installer->install(['display_builder_theme_test'], TRUE);
    $system_theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $system_theme_config->set('default', 'display_builder_theme_test')->save();

    // Set the state to use local asset libraries.
    $state = \Drupal::state();
    $state->set('display_builder.asset_libraries_local', TRUE);
  }

}
