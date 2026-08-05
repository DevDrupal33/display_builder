<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

/**
 * The ways a page layout can be seeded when it is created.
 *
 * A closed set on purpose. The three differ on a single axis, how much of what
 * already exists is kept, and that gradient is what teaches the model: only
 * the theme import keeps the page template of the front-end theme. A fourth
 * way in would blur it, so this is a type rather than a plugin type.
 *
 * @see \Drupal\display_builder_page_layout\StartingPoint::getSources()
 */
enum StartingPointType: string {

  // Copy the blocks placed in the front-end theme, in the theme page shell.
  case Theme = 'theme';

  // Place only the blocks a Drupal page needs to keep working.
  case Minimal = 'minimal';

  // Place nothing at all.
  case Blank = 'blank';

}
