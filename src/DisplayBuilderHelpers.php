<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\String\u;

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

      if (!file_exists($filepath)) {
        continue;
      }

      $content = file_get_contents($filepath);

      if (!$content) {
        continue;
      }

      return Yaml::decode($content);
    }

    return [];
  }

  /**
   * Load fixtures folder data.
   *
   * @param array $paths
   *   The paths to look in.
   * @param string|null $moduleName
   *   (Optional) Module name prefix.
   *
   * @return array
   *   The list of fixtures available.
   */
  public static function getFixturesOptions(array $paths, ?string $moduleName = NULL): array {
    $output = [];

    foreach ($paths as $path) {
      if (!file_exists($path)) {
        continue;
      }

      $finder = new Finder();
      $finder->files()->name('*.yml')->in($path);

      foreach ($finder as $file) {
        $name = $file->getFilenameWithoutExtension();
        $output[$name] = u(str_replace('_', ' ', $name))->title();
        if ($moduleName) {
          $output[$name] = \sprintf('[%s] %s', $moduleName, $output[$name]);
        }
      }
    }

    return $output;
  }

  /**
   * Load fixtures options from modules fixtures folder.
   *
   * @param array $moduleNames
   *   (Optional) The module names.
   *
   * @return array
   *   The list of fixtures available.
   */
  public static function getAllFixturesOptions(array $moduleNames = []): array {
    if (empty($moduleNames)) {
      $moduleNames = [
        'display_builder_devel',
        'display_builder_entity_view',
        'display_builder_views',
        'display_builder_page_layout',
      ];
    }
    $output = ['blank' => new TranslatableMarkup('Blank (Empty)')];
    $moduleHandler = \Drupal::moduleHandler();

    foreach ($moduleNames as $moduleName) {
      try {
        $path = $moduleHandler->getModule($moduleName)->getPath();
        $filepath = \sprintf('%s/%s/fixtures/', DRUPAL_ROOT, $path);
        $output = \array_merge($output, self::getFixturesOptions([$filepath], $moduleName));
      }
      catch (\Throwable $th) {
      }
    }

    return $output;
  }

  /**
   * Load fixtures options from modules fixtures folder.
   *
   * @param string $fixture_id
   *   The fixture file name.
   * @param array $moduleNames
   *   (Optional) The module names.
   *
   * @return array
   *   The list of fixtures available.
   */
  public static function getAllFixturesData(string $fixture_id, array $moduleNames = []): array {
    if (empty($moduleNames)) {
      $moduleNames = [
        'display_builder_devel',
        'display_builder_entity_view',
        'display_builder_views',
        'display_builder_page_layout',
      ];
    }

    foreach ($moduleNames as $moduleName) {
      $file = self::getFixtureDataFromModule($moduleName, '', $fixture_id);
      if (!empty($file)) {
        return $file;
      }
    }

    return [];
  }

  /**
   * Load YAML data from module fixtures folder for current theme.
   *
   * @param string $moduleName
   *   The module name.
   * @param string $suffix
   *   (Optional) The fixture file optional suffix.
   * @param string|null $fixture_id
   *   (Optional) The fixture file name.
   *
   * @return array
   *   The file content.
   */
  public static function getFixtureDataFromModule(string $moduleName, string $suffix = '', ?string $fixture_id = NULL): array {
    try {
      $path = \Drupal::moduleHandler()->getModule($moduleName)->getPath();
    }
    catch (\Throwable $th) {
      return [];
    }

    $defaultThemeName = \Drupal::configFactory()->get('system.theme')->get('default');

    if ($fixture_id) {
      $name = $fixture_id;
    }
    else {
      $name = \sprintf('%s_%s', $defaultThemeName, $suffix);
    }

    $filepath = \sprintf('%s/%s/fixtures/%s.yml', DRUPAL_ROOT, $path, $name);

    if (!file_exists($filepath)) {
      if (!$fixture_id) {
        $name = \sprintf('default_%s', $suffix);
      }
      $filepath = \sprintf('%s/%s/fixtures/%s.yml', DRUPAL_ROOT, $path, $name);
    }

    if (!file_exists($filepath)) {
      return [];
    }

    return self::getFixtureData([$filepath], TRUE);
  }

}
