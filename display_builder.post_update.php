<?php

/**
 * @file
 * Contains post update functions for the Display Builder module.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
 */

declare(strict_types=1);

use Drupal\display_builder\Update\ProfileIslandsUpdater;

/**
 * Delete all states after ContentEntityType migration.
 */
function display_builder_post_update_1(): void {
  $storage = \Drupal::service('entity_type.manager')->getStorage('display_builder_instance');
  $instances = $storage->loadMultiple();
  $storage->delete($instances);

  \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
}

/**
 * Migrate config with new values.
 */
function display_builder_post_update_2(): void {
  ProfileIslandsUpdater::create()
    // The 'layers' island is now named 'scaffold'.
    ->renameIsland('layers', 'scaffold')
    // The viewport island has no 'format' setting anymore.
    ->removeKey('format', ['viewport'])
    ->save();
}

/**
 * Migrate config with new values.
 */
function display_builder_post_update_3(): void {
  ProfileIslandsUpdater::create()
    // Placement is structural for every island type now, owned by the plugin
    // 'region' attribute, so no island stores a region anymore.
    // @see \Drupal\display_builder\Island\IslandType::regions()
    ->removeKey('region')
    ->save();
}
