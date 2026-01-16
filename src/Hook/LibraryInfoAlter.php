<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Alter libraries.
 */
class LibraryInfoAlter {

  /**
   * Alter libraries.
   *
   * @param array $libraries
   *   An associative array of libraries, passed by reference.
   * @param string $extension
   *   Can either be 'core' or the machine name of the extension that registered
   *   the libraries.
   */
  #[Hook('library_info_alter')]
  public function alter(array &$libraries, string $extension): void {
    if ($extension === 'navigation') {
      $libraries['navigation.layout']['dependencies'][] = 'display_builder/navigation';
    }
  }

}
