<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\Entity\EntityViewDisplay as CoreEntityViewDisplay;

/**
 * Provides an entity view display entity that has a display builder.
 */
class EntityViewDisplay extends CoreEntityViewDisplay implements DisplayBuilderEntityDisplayInterface {

  use EntityViewDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities): array {
    $build_list = parent::buildMultiple($entities);

    return $this->displayBuilderBuildMultiple($entities, $build_list);
  }

}
