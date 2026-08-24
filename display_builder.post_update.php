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

/**
 * Drop the label/icon display options and split the Controls island.
 */
function display_builder_post_update_5(): void {
  $config_factory = \Drupal::configFactory();

  foreach ($config_factory->listAll('display_builder.profile.') as $name) {
    $config = $config_factory->getEditable($name);
    $islands = $config->get('islands');

    if (!\is_array($islands)) {
      continue;
    }

    $islands = _display_builder_post_update_split_controls($islands);

    // Revert is the only button left with a say in whether it shows, and it is
    // a boolean now: a profile that had hidden it keeps it hidden.
    if (isset($islands['state'])) {
      $islands['state']['revert'] = ($islands['state']['revert']['value'] ?? 'label') !== 'hidden';
    }

    $config->set('islands', _display_builder_post_update_drop_button_values($islands));
    // Panels and tabs are labels only, so nothing stores how to show them.
    $config->clear('library_tabs_display')
      ->clear('contextual_tabs_display')
      ->clear('view_panels_display')
      ->save();
  }
}

/**
 * Turns the Controls island of a profile into one island per button.
 *
 * A site that turned a button off keeps it off through the island status of
 * that button rather than through a 'hidden' display value. The whole island
 * being disabled beats any per-button value it stored.
 *
 * @param array $islands
 *   The profile islands map.
 *
 * @return array
 *   The islands map, with 'controls' replaced by 'expand', 'theme' and 'help'.
 */
function _display_builder_post_update_split_controls(array $islands): array {
  if (!isset($islands['controls'])) {
    return $islands;
  }

  $controls = $islands['controls'];
  $enabled = !empty($controls['status']);

  foreach (['expand', 'theme', 'help'] as $button) {
    $islands[$button] ??= [
      'status' => $enabled && ($controls[$button]['value'] ?? 'hidden') !== 'hidden',
      'weight' => $controls['weight'] ?? 0,
    ];
  }

  unset($islands['controls']);

  return $islands;
}

/**
 * Drops every stored toolbar button display value.
 *
 * No button reads one anymore: every toolbar button is its label.
 *
 * @param array $islands
 *   The profile islands map.
 *
 * @return array
 *   The islands map without the '{button}: {value: …}' entries.
 */
function _display_builder_post_update_drop_button_values(array $islands): array {
  foreach ($islands as $island_id => $configuration) {
    if (!\is_array($configuration)) {
      continue;
    }

    foreach ($configuration as $key => $value) {
      if (\is_array($value) && \array_keys($value) === ['value']) {
        unset($islands[$island_id][$key]);
      }
    }
  }

  return $islands;
}
