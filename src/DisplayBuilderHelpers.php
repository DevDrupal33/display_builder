<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Serialization\Yaml;

/**
 * Helpers related class for Display builder.
 */
class DisplayBuilderHelpers {

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
    catch (\Throwable $th) {
    }

    if (!$path) {
      try {
        $path = \Drupal::service('theme_handler')->getTheme($name)->getPath();
      }
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

}
