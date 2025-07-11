<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;

/**
 * Hook implementations for the display_builder_page_layout module.
 */
class ThemeRegistryAlter {

  public function __construct(
    protected AdminContext $adminContext,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    if ($this->adminContext->isAdminRoute()) {
      return;
    }
    $template_uri = $this->moduleExtensionList->getPath('display_builder_page_layout') . '/templates';
    $theme_registry['html']['path'] = $template_uri;
    $theme_registry['page']['path'] = $template_uri;
    $theme_registry['region']['path'] = $template_uri;
    // $theme_registry['off_canvas_page_wrapper']['path'] = $template_uri;
  }

}
