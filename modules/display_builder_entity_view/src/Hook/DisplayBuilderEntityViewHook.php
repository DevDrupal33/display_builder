<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplayStorage;
use Drupal\display_builder_entity_view\Form\DisplayBuilderEntityViewDisplayForm;
use Drupal\layout_builder\Form\DefaultsEntityForm;

/**
 * Hook implementations for display_builder_entity_view.
 */
class DisplayBuilderEntityViewHook {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter', order: new OrderAfter(['layout_builder']))]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['entity_view_display']
      ->setClass(DisplayBuilderEntityViewDisplay::class)
      ->setStorageClass(DisplayBuilderEntityViewDisplayStorage::class)
      ->setFormClass('display_builder', DefaultsEntityForm::class)
      ->setFormClass('edit', DisplayBuilderEntityViewDisplayForm::class);
  }

}
