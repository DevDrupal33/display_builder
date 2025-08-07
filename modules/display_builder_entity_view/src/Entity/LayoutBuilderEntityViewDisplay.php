<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay as CoreLayoutBuilderEntityViewDisplay;

/**
 * Provides an entity view display entity that has a display builder.
 */
class LayoutBuilderEntityViewDisplay extends CoreLayoutBuilderEntityViewDisplay implements DisplayBuilderEntityDisplayInterface {

  use EntityViewDisplayTrait;

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities): array {
    $build_list = parent::buildMultiple($entities);

    // If using Layout Builder stop here.
    if ($this->isLayoutBuilderEnabled()) {
      return $build_list;
    }

    return $this->displayBuilderBuildMultiple($entities, $build_list);
  }

}
