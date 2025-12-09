<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Helpers related class for Display builder.
 */
class DisplayBuilderHelpers {

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

}
