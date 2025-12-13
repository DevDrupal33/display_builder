<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Helpers related class for Display builder.
 */
class DisplayBuilderHelpers {

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
   * Determines if a given entity type is display builder relevant or not.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return bool
   *   Whether this entity type is a display builder candidate or not.
   */
  public static function isDisplayBuilderEntityType(EntityTypeInterface $entityType): bool {
    return $entityType->entityClassImplements(FieldableEntityInterface::class)
      && $entityType->hasViewBuilderClass();
  }

  /**
   * Recursively search and replace values in a multi-dimensional array.
   *
   * This function traverses an array and replaces elements based on a set of
   * search criteria. It's optimized to perform multiple replacements in a
   * single pass.
   *
   * @param array &$array
   *   The array to search and replace in (passed by reference).
   * @param array $replacements
   *   An array of replacement rules. Each rule is an associative array with:
   *   - 'search': An associative array with a single key-value pair to find.
   *               Example: ['plugin_id' => 'system_messages_block'].
   *   - 'new_value': The value to replace the matched element with.
   */
  public static function findAndReplaceInArray(array &$array, array $replacements): void {
    foreach ($array as $key => &$value) {
      if (!\is_array($value)) {
        continue;
      }

      foreach ($replacements as $replacement) {
        $search = $replacement['search'];
        $class = $replacement['new_value_class'] ?? '';
        $newValue = [
          'source_id' => 'token',
          'source' => [
            'value' => '<div class="db-background db-preview-placeholder ' . $class . '">' . $replacement['new_value_title'] . '</div>',
          ],
        ];
        $searchKey = \array_key_first($search);
        $searchValue = $search[$searchKey] ?? NULL;

        $match = FALSE;

        // Match "source_id" directly on the child.
        if ($searchKey === 'source_id' && isset($value['source_id']) && $value['source_id'] === $searchValue) {
          $match = TRUE;
        }
        // Match "plugin_id" either directly on the child or inside its 'source'
        // sub-array.
        elseif ($searchKey === 'plugin_id') {
          if ((isset($value['plugin_id']) && $value['plugin_id'] === $searchValue)
            || (isset($value['source']) && \is_array($value['source']) && isset($value['source']['plugin_id']) && $value['source']['plugin_id'] === $searchValue)
          ) {
            $match = TRUE;
          }
        }

        if ($match) {
          $array[$key] = $newValue;

          // Item replaced, continue to next item in the main array to avoid
          // unnecessary recursion into the new value or other replacements.
          continue 2;
        }
      }

      // Recurse into deeper arrays if no replacement was made at this level.
      self::findAndReplaceInArray($value, $replacements);
    }
    // Break the reference to the last iterated value.
    unset($value);
  }

  /**
   * Multi-array search and replace parent.
   *
   * @param array $array
   *   The array to search in.
   * @param array $search
   *   The key value to replace.
   * @param mixed $new_value
   *   The new value to set.
   */
  public static function findArrayReplaceSource(array &$array, array $search, mixed $new_value): void {
    foreach ($array as $key => $value) {
      if (\is_array($value) && \is_array($array[$key])) {
        self::findArrayReplaceSource($array[$key], $search, $new_value);
      }
      elseif ([$key => $value] === $search) {
        $array['source'] = $new_value;
      }
    }
  }

  /**
   * Load YAML data if found in fixtures folder.
   *
   * @param array $filepaths
   *   The fixture file paths.
   * @param bool $extension
   *   (Optional) The filepath include extension. Default FALSE.
   *
   * @return array
   *   The file content.
   */
  public static function getFixtureData(array $filepaths, bool $extension = FALSE): array {
    foreach ($filepaths as $filepath) {
      if (!$extension) {
        $filepath = $filepath . '.yml';
      }

      if (!\file_exists($filepath)) {
        continue;
      }

      $content = \file_get_contents($filepath);

      if (!$content) {
        continue;
      }

      return Yaml::decode($content);
    }

    return [];
  }

  /**
   * Load YAML data from fixtures folder for current theme.
   *
   * @param string $name
   *   The extension name.
   * @param string|null $fixture_id
   *   (Optional) The fixture file name.
   *
   * @return array
   *   The file content.
   */
  public static function getFixtureDataFromExtension(string $name, ?string $fixture_id = NULL): array {
    $path = NULL;

    try {
      $path = \Drupal::moduleHandler()->getModule($name)->getPath();
    }
    // @phpcs:ignore SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired
    catch (\Throwable $th) {
    }

    if (!$path) {
      try {
        $path = \Drupal::service('theme_handler')->getTheme($name)->getPath();
      }
      // @phpcs:ignore SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired
      catch (\Throwable $th) {
      }
    }

    if (!$path) {
      return [];
    }

    $filepath = \sprintf('%s/%s/fixtures/%s.yml', DRUPAL_ROOT, $path, $fixture_id);

    if (!\file_exists($filepath)) {
      return [];
    }

    return self::getFixtureData([$filepath], TRUE);
  }

  /**
   * Format the log.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $log
   *   The log to format.
   *
   * @return array
   *   The formatted log.
   */
  public static function formatLog(TranslatableMarkup $log): array {
    return ['#markup' => Markup::create($log->render())];
  }

  /**
   * Print the date for humans.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param int $timestamp
   *   The timestamp integer.
   *
   * @return string
   *   The formatted date.
   */
  public static function formatTime(DateFormatterInterface $dateFormatter, int $timestamp): string {
    $now = \time();

    // Delta based on midnight today to not include day before.
    $midnightToday = \strtotime('today');
    $deltaToday = $now - $midnightToday;

    $deltaEvent = $now - $timestamp;

    if ($deltaEvent <= $deltaToday) {
      return $dateFormatter->format($timestamp, 'custom', 'G:i');
    }

    return $dateFormatter->format($timestamp, 'short');
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
