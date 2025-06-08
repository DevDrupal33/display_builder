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
      self::View->value => new TranslatableMarkup('Islands displayable as a main area tabs or as a offcanvas sidebar.'),
      self::Library->value => new TranslatableMarkup('Islands available in the Library island.'),
      self::Button->value => new TranslatableMarkup('Toolbar buttons.'),
      self::Contextual->value => new TranslatableMarkup('Islands visible only when the related instance is active.'),
      self::Menu->value => new TranslatableMarkup('Islands that act on the contextual menu, providing specific entries.'),
      default => new TranslatableMarkup('Unknown island type.'),
    };
  }

}

/**
 * List the island sub types for IslandType::View.
 */
enum IslandTypeViewDisplay: string {

  case Sidebar = 'sidebar';
  case Main = 'main';

  /**
   * Get the type list options.
   *
   * @return array
   *   The type list options as key => description.
   */
  public static function options(): array {
    return [
      IslandTypeViewDisplay::Sidebar->value => new TranslatableMarkup('Sidebar (OffCanvas)'),
      IslandTypeViewDisplay::Main->value => new TranslatableMarkup('Main area (Tabs in the toolbar)'),
    ];
  }

}
