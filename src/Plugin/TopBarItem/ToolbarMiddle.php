<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\TopBarItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarRegion;

/**
 * Display Builder toolbar middle.
 */
#[TopBarItem(
  id: 'display_builder_toolbar_middle',
  region: TopBarRegion::Context,
  label: new TranslatableMarkup('Display Builder toolbar middle'),
)]
class ToolbarMiddle extends ToolbarBase {

}
