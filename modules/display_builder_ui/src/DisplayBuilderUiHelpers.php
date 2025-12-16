<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;

/**
 * Helpers related class for Display builder ui.
 *
 * @todo replace with better entities handling.
 */
class DisplayBuilderUiHelpers {

  /**
   * Collect instances from definitions.
   *
   * @param array $providers
   *   Providers information.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @return array
   *   List of instances indexed by id.
   */
  public static function guessInstancesList(array $providers, EntityTypeManagerInterface $entityTypeManager): array {
    $instances = [];

    $instances = \array_merge($instances, self::collectPageLayoutInstances($entityTypeManager));
    $instances = \array_merge($instances, self::collectViewInstances($providers, $entityTypeManager));
    $instances = \array_merge($instances, self::collectEntityViewInstances($providers, $entityTypeManager));

    // Attach the runtime instances when available.
    $instances = self::attachLoadedInstances($instances, $entityTypeManager);

    return $instances;
  }

  /**
   * Collect page layout instances.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   Array of instances indexed by instance id. Each item includes keys
   *   'id', 'instance', 'context' and 'meta' (with 'page_layout').
   */
  private static function collectPageLayoutInstances(EntityTypeManagerInterface $entityTypeManager): array {
    $instances = [];
    $storage = $entityTypeManager->getStorage('page_layout');
    $page_layouts = $storage->loadMultiple();

    foreach ($page_layouts as $page_layout) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      $instance_id = $page_layout->getInstanceId();
      $instances[$instance_id] = [
        'id' => $instance_id,
        'instance' => NULL,
        'context' => 'page_layout',
        'meta' => [
          'page_layout' => $page_layout,
        ],
      ];
    }

    return $instances;
  }

  /**
   * Collect view instances.
   *
   * @param array $providers
   *   Providers information as returned by the module hook.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   Array of instances indexed by id. Each item contains 'id', 'instance',
   *   'context' and 'meta' with 'view_id' and 'display_id'.
   */
  private static function collectViewInstances(array $providers, EntityTypeManagerInterface $entityTypeManager): array {
    $instances = [];
    $storage = $entityTypeManager->getStorage('view');
    $views = $storage->loadMultiple();

    foreach ($views as $view) {
      // @phpstan-ignore-next-line
      foreach ($view->display as $display_id => $display) {
        $profile = $display['display_options']['display_extenders']['display_builder']['profile'] ?? NULL;

        if (!$profile) {
          continue;
        }
        $instance_id = \sprintf('%s%s__%s', $providers['views']['prefix'], $view->id(), $display_id);
        $instances[$instance_id] = [
          'id' => $instance_id,
          'instance' => NULL,
          'context' => 'view',
          'meta' => [
            'view_id' => $view->id(),
            'display_id' => $display_id,
          ],
        ];
      }
    }

    return $instances;
  }

  /**
   * Collect entity view display and override instances.
   *
   * @param array $providers
   *   Providers information as returned by the module hook.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   Array of instances indexed by id. Each item contains 'id', 'instance',
   *   'context' and 'meta' with entity related data (type, bundle, display,
   *   or override field data).
   */
  private static function collectEntityViewInstances(array $providers, EntityTypeManagerInterface $entityTypeManager): array {
    $instances = [];
    $storage = $entityTypeManager->getStorage('entity_view_display');
    $displays = $storage->loadMultiple();
    $entity_storage = $entity_query = [];

    foreach ($displays as $display_id => $display) {
      $display_builder = $display->getThirdPartySettings('display_builder');
      [$entity_type_id, $bundle, $entity_display] = \explode('.', $display_id);

      // Find simple entity display enabled.
      if (!empty($display_builder['profile'] ?? NULL)) {
        $instance_id = \sprintf('%s%s', $providers['entity_view']['prefix'], \str_replace('.', '__', $display_id));
        $instances[$instance_id] = [
          'id' => $instance_id,
          'instance' => NULL,
          'context' => 'entity_view',
          'meta' => [
            'entity_type_id' => $entity_type_id,
            'bundle' => $bundle,
            'entity_display' => $entity_display,
          ],
        ];
      }

      // Find entity override display enabled with value.
      if (isset($display_builder[ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY], $display_builder[ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY])) {
        $type = $display->getTargetEntityTypeId();
        $field_name = $display_builder[ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY] ?? NULL;

        if (!$field_name) {
          continue;
        }

        if (!isset($entity_storage[$type])) {
          $entity_storage[$type] = $entityTypeManager->getStorage($type);
        }

        if (!isset($entity_query[$type])) {
          $entity_query[$type] = $entity_storage[$type]->getQuery()->accessCheck(FALSE);
        }
        $entity_query[$type]->exists($field_name);
        $ids = $entity_query[$type]->execute();

        if (empty($ids)) {
          continue;
        }

        foreach ($ids as $id) {
          $instance_id = \sprintf('%s%s__%s__%s',
            $providers['entity_view_override']['prefix'],
            $type,
            $id,
            $field_name,
          );

          $instances[$instance_id] = [
            'id' => $instance_id,
            'instance' => NULL,
            'context' => 'entity_view_display',
            'meta' => [
              'type' => $type,
              'id' => $id,
              'field_name' => $field_name,
              'entity_display' => $entity_display,
            ],
          ];
        }
      }
    }

    return $instances;
  }

  /**
   * Attach the runtime (stored) Display Builder instances to a collection.
   *
   * This looks up `display_builder_instance` entities for the collected
   * instance ids and sets the 'instance' key to the loaded entity where
   * applicable.
   *
   * @param array $instances
   *   Instances array as returned by the collector methods.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   The original instances array with the 'instance' key populated where a
   *   runtime entity exists.
   */
  private static function attachLoadedInstances(array $instances, EntityTypeManagerInterface $entityTypeManager): array {
    $instance_storage = $entityTypeManager->getStorage('display_builder_instance');
    $loaded_instances = $instance_storage->loadMultiple(\array_keys($instances));

    foreach (\array_keys($instances) as $instance_id) {
      if (isset($loaded_instances[$instance_id])) {
        $instances[$instance_id]['instance'] = $loaded_instances[$instance_id];
      }
    }

    return $instances;
  }

}
