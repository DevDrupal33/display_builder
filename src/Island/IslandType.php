<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * List the island types for display builder.
 */
enum IslandType: string {

  // Islands acting as part of view island.
  case Library = 'library';
  case Contextual = 'contextual';
  case View = 'view';
  case Preview = 'preview';
  case Button = 'button';
  case Menu = 'menu';
  case Floating = 'floating';

  /**
   * Get the string description for this enum.
   *
   * @param string $type
   *   The enum string name.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The enum string description.
   */
  public static function description(string $type): TranslatableMarkup {
    return match ($type) {
      self::View->value => new TranslatableMarkup('Panels shown as a main area tab or as a sidebar.'),
      self::Preview->value => new TranslatableMarkup('Preview panel, meant to be a single panel.'),
      self::Library->value => new TranslatableMarkup('Panels available as tab in the Library panel.'),
      self::Button->value => new TranslatableMarkup('Toolbar buttons allowing direct actions in the builder.'),
      self::Contextual->value => new TranslatableMarkup('Panels visible only when the a source is selected.'),
      self::Menu->value => new TranslatableMarkup('Items available in the contextual menu.'),
      self::Floating->value => new TranslatableMarkup('Floating controls, attached to one or more View panels, only visible while an attached panel is the active main tab.'),
      default => new TranslatableMarkup('Unknown island type.'),
    };
  }

  /**
   * Get the regions a type is split into.
   *
   * Placement is structural, owned by the plugin 'region' attribute, never a
   * profile level preference: the regions of a type are built differently from
   * one another (a narrow sidebar drawer versus a full width tab), so an
   * island belongs to one of them the way it belongs to its type. This list
   * exists to group them for display, not to offer a choice.
   *
   * @param string $type
   *   The island type value.
   *
   * @return array
   *   The type regions as key => description, empty for a type with a single
   *   region.
   */
  public static function regions(string $type): array {
    return match ($type) {
      self::View->value => [
        'sidebar' => new TranslatableMarkup('Sidebar'),
        'main' => new TranslatableMarkup('Main area (Tabs)'),
      ],
      self::Button->value => [
        'start' => new TranslatableMarkup('Start'),
        'end' => new TranslatableMarkup('End'),
      ],
      default => [],
    };
  }

  /**
   * Get the region an island of this type lands in without declaring one.
   *
   * @param string $type
   *   The island type value.
   *
   * @return string|null
   *   The fallback region, NULL for a type with a single region.
   */
  public static function defaultRegion(string $type): ?string {
    return match ($type) {
      self::View->value => 'main',
      self::Button->value => 'end',
      default => NULL,
    };
  }

}
