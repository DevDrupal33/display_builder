<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\TopBarItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarRegion;

/**
 * Display Builder toolbar end.
 */
#[TopBarItem(
  id: 'display_builder_toolbar_end',
  region: TopBarRegion::Actions,
  label: new TranslatableMarkup('Display Builder toolbar end'),
)]
class ToolbarEnd extends ToolbarBase {

}
