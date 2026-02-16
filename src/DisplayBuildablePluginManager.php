<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\display_builder\Attribute\DisplayBuildable;

/**
 * DisplayBuildable plugin manager.
 */
final class DisplayBuildablePluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/display_builder/Buildable', $namespaces, $module_handler, DisplayBuildableInterface::class, DisplayBuildable::class);
    $this->alterInfo('display_buildable_info');
    $this->setCacheBackend($cache_backend, 'display_buildable_plugins');
  }

}
