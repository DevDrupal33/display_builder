<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuilderHelpers;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\String\u;

/**
 * Helpers for fixtures management.
 */
class FixturesHelpers {

  /**
   * Default modules to look for fixtures.
   */
  private static array $moduleNames = [
    'display_builder_devel',
    'display_builder_entity_view',
    'display_builder_views',
    'display_builder_page_layout',
  ];

  /**
   * Default modules to look for fixtures.
   */
  private static array $themeNames = [
    'db_theme_test',
  ];

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
      $moduleNames = self::$moduleNames;
    }
    $output = ['blank' => new TranslatableMarkup('Blank (Empty)')];

    foreach ($moduleNames as $moduleName) {
      try {
        $path = \Drupal::moduleHandler()->getModule($moduleName)->getPath();
        $filepath = \sprintf('%s/%s/fixtures/', DRUPAL_ROOT, $path);
        $output = \array_merge($output, self::getFixturesOptions([$filepath], $moduleName));
      }
      // @phpcs:ignore SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired
      catch (\Throwable $th) {
      }
    }

    $themeHandler = \Drupal::service('theme_handler');

    foreach (self::$themeNames as $themeName) {
      try {
        if (($themeHandler->getTheme($themeName)->status ?? 0) !== 1) {
          continue;
        }
        $path = $themeHandler->getTheme($themeName)->getPath();
        $filepath = \sprintf('%s/%s/fixtures/', DRUPAL_ROOT, $path);
        $output = \array_merge($output, self::getFixturesOptions([$filepath], $themeName));
      }
      // @phpcs:ignore SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired
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
   * @param array $names
   *   (Optional) The extension names.
   *
   * @return array
   *   The list of fixtures available.
   */
  public static function getAllFixturesData(string $fixture_id, array $names = []): array {
    if (empty($names)) {
      $names = \array_merge(self::$moduleNames, self::$themeNames);
    }

    foreach ($names as $name) {
      $file = DisplayBuilderHelpers::getFixtureDataFromExtension($name, $fixture_id);

      if (!empty($file)) {
        return $file;
      }
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
  private static function getFixturesOptions(array $paths, ?string $moduleName = NULL): array {
    $output = [];

    foreach ($paths as $path) {
      if (!\file_exists($path)) {
        continue;
      }

      $finder = new Finder();
      $finder->files()->name('*.yml')->in($path);

      foreach ($finder as $file) {
        $name = $file->getFilenameWithoutExtension();
        $output[$name] = u(\str_replace('_', ' ', $name))->title();

        if ($moduleName) {
          $output[$name] = \sprintf('[%s] %s', $moduleName, $output[$name]);
        }
      }
    }

    return $output;
  }

}
