<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helpers related class for Display builder.
 */
class DisplayBuilderHelpers {

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
   * The display a request is a preview sub-request for, if it is one.
   *
   * Four things need to know: the controller opening the sub-request guards
   * against nesting, two caches refuse to serve or store it, and the display
   * being previewed swaps its saved sources for the draft. They all read the
   * one attribute, so they read it here.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request, usually the current one. NULL is answered, not guarded
   *   against, because the request stack is empty outside a request.
   *
   * @return string|null
   *   The previewed instance ID, or NULL when this is an ordinary request.
   *
   * @see \Drupal\display_builder\Controller\ApiPreviewController::renderOnPinnedPage()
   */
  public static function previewedInstanceId(?Request $request): ?string {
    $instance_id = $request?->attributes->get(DisplayBuildableInterface::PREVIEW_INSTANCE_ATTRIBUTE);

    return \is_string($instance_id) ? $instance_id : NULL;
  }

  /**
   * Marks a sample entity as a preview, the way core's own previews do.
   *
   * A sample entity is never saved, so it has no ID, and a formatter that
   * needs one has nothing to work with: the comment field's "Add comment"
   * form loads the commented entity by ID and asserts its way out on NULL,
   * taking down the render of everything around it. `in_preview` is the flag
   * core already uses to say "not a real page, stand down" - node preview
   * sets it, and comment, history and content_moderation all check it.
   *
   * Only sample entities get it. A display bound to a real entity is a real
   * page and must render like one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity, saved or sample.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The same entity, for chaining into a context.
   *
   * @see \Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter::viewElements()
   */
  public static function markSampleEntity(EntityInterface $entity): EntityInterface {
    if ($entity->id() === NULL) {
      // Undeclared on purpose, by core: `in_preview` is a plain dynamic
      // property that NodeForm and CommentForm set the same way.
      // @phpstan-ignore property.notFound
      $entity->in_preview = TRUE;
    }

    return $entity;
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
