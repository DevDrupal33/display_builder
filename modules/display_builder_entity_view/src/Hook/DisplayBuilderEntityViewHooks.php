<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for display_builder_entity_view.
 */
class DisplayBuilderEntityViewHooks {

  /**
   * Implements hook_preprocess_entity().
   *
   * Special callback function for entity.html.twig.
   *
   * Reproduce Core preprocesses for node, media, taxonomy_term to populate the
   * content variable.
   */
  #[Hook('preprocess_entity')]
  public static function preprocessEntity(array &$variables): void {
    $variables += [
      'content' => [],
    ];

    if (isset($variables['elements']['content'])) {
      $variables['content']['content'] = $variables['elements']['content'];
    }
  }

}
