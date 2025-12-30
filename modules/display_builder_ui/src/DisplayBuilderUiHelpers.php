<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;

/**
 * Helpers related class for Display builder ui.
 *
 * @todo replace with better entities handling.
 */
class DisplayBuilderUiHelpers {

  /**
   * Get instances from providers definitions.
   *
   * @param array $providers
   *   Providers information.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @return array
   *   List of instances indexed by id.
   */
  public static function getInstancesFromProviders(array $providers, EntityTypeManagerInterface $entityTypeManager): array {
    $instances = [];

    $instance_storage = $entityTypeManager->getStorage('display_builder_instance');
    /** @var array<string, \Drupal\display_builder\InstanceInterface> $state_instances */
    $state_instances = $instance_storage->loadMultiple();

    foreach ($providers as $provider_id => $provider) {
      if ($provider['storage']) {
        $storage = $entityTypeManager->getStorage($provider['storage']);
        $entities = $storage->loadMultiple();

        switch ($provider_id) {
          case 'page_layout':
            $instances = \array_merge($instances, self::collectPageLayoutInstances($entities, $state_instances));

            break;

          case 'entity_view':
            $instances = \array_merge($instances, self::collectEntityViewInstances($entities, $state_instances, $providers, $entityTypeManager));

            break;

          case 'views':
            $instances = \array_merge($instances, self::collectViewInstances($entities, $state_instances, $provider['prefix']));

            break;

          default:
            $instances = \array_merge($instances, self::collectInstances($entities, $state_instances, $provider['prefix']));

            break;
        }
      }
    }

    return $instances;
  }

  /**
   * Collect page layout instances.
   *
   * @param array<string, \Drupal\Core\Entity\EntityInterface> $entities
   *   The entities that hold an instance.
   * @param array<string, \Drupal\display_builder\InstanceInterface> $state_instances
   *   The state instances.
   * @param string $prefix
   *   The provider prefix.
   *
   * @return array
   *   Array of instances indexed by instance id. Each item includes keys
   *   'id', 'instance', 'context'.
   */
  private static function collectInstances(array $entities, array $state_instances, string $prefix): array {
    $instances = [];

    foreach ($entities as $entity) {
      /** @var \Drupal\display_builder\InstanceInterface $entity */
      $instance_id = $entity->id ?? '';

      if (!\str_starts_with($instance_id, $prefix)) {
        continue;
      }
      $instances[$instance_id] = [
        'id' => $instance_id,
        'instance' => $state_instances[$instance_id] ?? NULL,
        'context' => 'devel',
      ];
    }

    return $instances;
  }

  /**
   * Collect page layout instances.
   *
   * @param array<string, \Drupal\Core\Entity\EntityInterface> $entities
   *   The entities that hold an instance.
   * @param array<string, \Drupal\display_builder\InstanceInterface> $state_instances
   *   The state instances.
   *
   * @return array
   *   Array of instances indexed by instance id. Each item includes keys
   *   'id', 'instance', 'context'.
   */
  private static function collectPageLayoutInstances(array $entities, array $state_instances): array {
    $instances = [];

    foreach ($entities as $page_layout) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      $instance_id = $page_layout->getInstanceId();
      $instances[$instance_id] = [
        'id' => $instance_id,
        'instance' => $state_instances[$instance_id] ?? NULL,
        'context' => 'page_layout',
      ];
    }

    return $instances;
  }

  /**
   * Collect view instances.
   *
   * @param array<string, \Drupal\Core\Entity\EntityInterface> $entities
   *   The entities that hold an instance.
   * @param array<string, \Drupal\display_builder\InstanceInterface> $state_instances
   *   The state instances.
   * @param string $prefix
   *   The provider prefix.
   *
   * @return array
   *   Array of instances indexed by id. Each item contains 'id', 'instance',
   *   'context'.
   */
  private static function collectViewInstances(array $entities, array $state_instances, string $prefix): array {
    $instances = [];

    foreach ($entities as $view) {
      // @phpstan-ignore-next-line
      foreach ($view->display as $display_id => $display) {
        $profile = $display['display_options']['display_extenders']['display_builder']['profile'] ?? NULL;

        if (!$profile) {
          continue;
        }
        $instance_id = \sprintf('%s%s__%s', $prefix, $view->id(), $display_id);
        $instances[$instance_id] = [
          'id' => $instance_id,
          'instance' => $state_instances[$instance_id] ?? NULL,
          'context' => 'view',
        ];
      }
    }

    return $instances;
  }

  /**
   * Collect entity view display and override instances.
   *
   * @param array<string, \Drupal\Core\Entity\EntityInterface> $entities
   *   The entities that hold an instance.
   * @param array<string, \Drupal\display_builder\InstanceInterface> $state_instances
   *   The state instances.
   * @param array $providers
   *   Providers information as returned by the module hook.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   Array of instances indexed by id. Each item contains 'id', 'instance',
   *   'context'.
   */
  private static function collectEntityViewInstances(array $entities, array $state_instances, array $providers, EntityTypeManagerInterface $entityTypeManager): array {
    $instances = [];
    $entity_storage = $entity_query = [];

    foreach ($entities as $display_id => $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $display_builder = $display->getThirdPartySettings('display_builder');

      // Find simple entity display enabled.
      if (!empty($display_builder['profile'] ?? NULL)) {
        $instance_id = \sprintf('%s%s', $providers['entity_view']['prefix'], \str_replace('.', '__', $display_id));
        $instances[$instance_id] = [
          'id' => $instance_id,
          'instance' => $state_instances[$instance_id] ?? NULL,
          'context' => 'entity_view',
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
          ];
        }
      }
    }

    return $instances;
  }

}
