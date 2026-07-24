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
 * Migrate legacy profile island config from layers to scaffold.
 */
function display_builder_post_update_2(): void {
  $storage = \Drupal::service('entity_type.manager')->getStorage('display_builder_profile');
  $profiles = $storage->loadMultiple();

  foreach ($profiles as $profile) {
    if (!$profile instanceof ProfileInterface) {
      continue;
    }

    $islands = $profile->get('islands');
    if (!is_array($islands) || !isset($islands['layers']) || isset($islands['scaffold'])) {
      continue;
    }

    $islands['scaffold'] = $islands['layers'];
    unset($islands['layers']);
    $profile->set('islands', $islands);
    $profile->save();
  }
}
