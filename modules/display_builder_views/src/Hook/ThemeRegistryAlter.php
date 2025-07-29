<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;

/**
 * Hook implementations for the display_builder_views module.
 */
class ThemeRegistryAlter {

  public function __construct(
    protected AdminContext $adminContext,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Implements hook_theme_registry_alter().
   *
   * @param array $theme_registry
   *   The theme registry to alter.
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    if ($this->adminContext->isAdminRoute()) {
      return;
    }
    $template_uri = $this->moduleExtensionList->getPath('display_builder_views') . '/templates';
    $theme_registry['views_view']['path'] = $template_uri;
  }

}
