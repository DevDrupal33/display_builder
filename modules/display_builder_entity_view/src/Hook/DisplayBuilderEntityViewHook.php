<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\Form\EntityViewDisplayForm;
use Drupal\display_builder_entity_view\Form\LayoutBuilderEntityViewDisplayForm;

/**
 * Hook implementations for display_builder_entity_view.
 */
class DisplayBuilderEntityViewHook {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter', order: new OrderAfter(['layout_builder']))]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    if ($this->moduleHandler->moduleExists('layout_builder')) {
      $entity_types['entity_view_display']
        ->setClass(LayoutBuilderEntityViewDisplay::class)
        ->setFormClass('edit', LayoutBuilderEntityViewDisplayForm::class);
    }
    else {
      $entity_types['entity_view_display']
        ->setClass(EntityViewDisplay::class)
        ->setFormClass('edit', EntityViewDisplayForm::class);
    }
  }

}
