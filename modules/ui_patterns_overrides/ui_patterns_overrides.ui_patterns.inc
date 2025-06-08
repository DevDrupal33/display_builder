<?php

/**
 * @file
 * Contains the display builder module.
 */

declare(strict_types=1);

use Drupal\display_builder\RenderableAltererInterface;
use Drupal\ui_patterns\SourceInterface;

/**
 * Alters the renderable array for a component that has been built.
 *
 * @param mixed &$build
 *   The renderable array of the component.
 * @param \Drupal\ui_patterns\SourceInterface $source
 *   The data array containing information about the component.
 * @param array $source_configuration
 *   The full raw configuration used to build the source.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function ui_patterns_overrides_ui_patterns_source_value_alter(mixed &$build, SourceInterface $source, array &$source_configuration): void {
  if (!isset($source_configuration['_third_party_settings'])) {
    return;
  }

  $islandManager = Drupal::service('plugin.manager.db_island');

  foreach ($source_configuration['_third_party_settings'] as $island_id => $settings) {
    $island = $islandManager->createInstance($island_id);

    if ($build && $island instanceof RenderableAltererInterface) {
      $build = $island->alterElement($build, $settings);
    }
  }
}
