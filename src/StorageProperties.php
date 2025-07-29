<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Storage properties for config.
 */
enum StorageProperties: string {

  // The entity ID of a Display builder config entity.
  case ConfigEntityId = 'display_builder';

  // A nestable list of UI Patterns 2 sources.
  case Sources = 'sources';

}
