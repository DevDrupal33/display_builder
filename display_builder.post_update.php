<?php

/**
 * @file
 * Contains post update functions for the Display Builder module.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
 */

declare(strict_types=1);

use Drupal\display_builder\Entity\ProfileInterface;
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

/**
 * Enable the Instances panel on profiles that predate it.
 */
function display_builder_post_update_4(): void {
  $storage = \Drupal::service('entity_type.manager')->getStorage('display_builder_profile');

  foreach ($storage->loadMultiple() as $profile) {
    if (!$profile instanceof ProfileInterface) {
      continue;
    }

    $islands = $profile->get('islands');
    if (!is_array($islands) || isset($islands['instances'])) {
      continue;
    }

    // The traversal primitive for the nesting problem: without it, moving
    // between the levels a page is assembled from means leaving the builder.
    //
    // First in the sidebar, as it is on a fresh install, but reached by going
    // one below whatever the profile already has rather than by copying the
    // shipped weight: an existing profile may have been reordered, and writing
    // -10 onto a site whose Library is already at -10 settles the order by a
    // tie nobody chose. Nothing else moves.
    //
    // Region resolved the way ProfileForm resolves it, config first and the
    // plugin's default_region behind it, because a profile saved through that
    // form carries no region key at all and comparing against the ones that do
    // would find nothing to go below.
    $definitions = \Drupal::service('plugin.manager.db_island')->getDefinitions();
    $weights = [];

    foreach ($islands as $island_id => $configuration) {
      $region = $configuration['region'] ?? $definitions[$island_id]['default_region'] ?? NULL;

      if ($region === 'sidebar') {
        $weights[] = (int) ($configuration['weight'] ?? 0);
      }
    }

    $islands['instances'] = [
      'status' => TRUE,
      'weight' => $weights === [] ? 0 : \min($weights) - 1,
      'region' => 'sidebar',
    ];
    $profile->set('islands', $islands);
    $profile->save();
  }
}
