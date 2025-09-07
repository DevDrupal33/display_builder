<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder_entity_view\DisplayBuilderEnabledInterface;

/**
 * Provides an interface for entity displays that have Display Builder.
 */
interface DisplayBuilderEntityDisplayInterface extends DisplayBuildableInterface, DisplayBuilderEnabledInterface, EntityDisplayInterface {

}
