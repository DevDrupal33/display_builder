<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * List the island types for display builder.
 */
enum IslandType: string {

  // Islands acting as part of view island.
  case Library = 'library';
  case Contextual = 'contextual';
  case View = 'view';
  case Button = 'button';
  case Menu = 'menu';

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
      self::Library->value => new TranslatableMarkup('Panels available as tab in the Library panel.'),
      self::Button->value => new TranslatableMarkup('Toolbar buttons allowing direct actions in the builder.'),
      self::Contextual->value => new TranslatableMarkup('Panels visible only when the a source is selected.'),
      self::Menu->value => new TranslatableMarkup('Items available in the contextual menu.'),
      default => new TranslatableMarkup('Unknown island type.'),
    };
  }

  /**
   * Get the available regions by type.
   *
   * @return array
   *   The type regions as key => description.
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

}
