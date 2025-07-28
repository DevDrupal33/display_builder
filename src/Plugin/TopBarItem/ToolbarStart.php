<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\TopBarItem;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarRegion;

/**
 * Display Builder toolbar start.
 */
#[TopBarItem(
  id: 'display_builder_toolbar_start',
  region: TopBarRegion::Tools,
  label: new TranslatableMarkup('Display Builder toolbar start'),
)]
class ToolbarStart extends ToolbarBase {

}
