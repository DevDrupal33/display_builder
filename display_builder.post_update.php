<?php

/**
 * @file
 * Contains post update functions for the Display Builder module.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
 */

declare(strict_types=1);

use Drupal\display_builder\Entity\ProfileInterface;

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
  $storage = \Drupal::service('entity_type.manager')->getStorage('display_builder_profile');
  $profiles = $storage->loadMultiple();

  foreach ($profiles as $profile) {
    if (!$profile instanceof ProfileInterface) {
      continue;
    }

    $islands = $profile->get('islands');
    if (!is_array($islands)) {
      continue;
    }
    $changed = FALSE;

    // The 'layers' island is now named 'scaffold'.
    if (isset($islands['layers']) && !isset($islands['scaffold'])) {
      $islands['scaffold'] = $islands['layers'];
      unset($islands['layers']);
      $changed = TRUE;
    }

    // The viewport island has no 'format' setting anymore.
    if (is_array($islands['viewport'] ?? NULL) && array_key_exists('format', $islands['viewport'])) {
      unset($islands['viewport']['format']);
      $changed = TRUE;
    }

    // The preview island is no longer a region aware View island.
    if (is_array($islands['preview'] ?? NULL) && array_key_exists('region', $islands['preview'])) {
      unset($islands['preview']['region']);
      $changed = TRUE;
    }

    if (!$changed) {
      continue;
    }

    $profile->set('islands', $islands);
    $profile->save();
  }
}
