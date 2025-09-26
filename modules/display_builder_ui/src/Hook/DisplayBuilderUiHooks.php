<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for display_builder_ui.
 */
class DisplayBuilderUiHooks {

  /**
   * Alter the entity type definitions.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   Entity types definitions.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $entity_types['pattern_preset']->setLinkTemplate('edit-form', '/admin/structure/display-builder/preset/{pattern_preset}');
  }

}
