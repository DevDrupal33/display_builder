<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\DisplayBuilderHelpers;
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
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   *
   * @param array $entity_types
   *   An associative array of entity type definitions.
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

  /**
   * Implements hook_entity_delete().
   *
   * If the entity deleted has a display override, need to be deleted as well.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $entity_type_id = $entity->getEntityTypeId();

    if ($entity_type_id === 'display_builder_instance') {
      return;
    }
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    // Only entities with display are concerned.
    if (!DisplayBuilderHelpers::isDisplayBuilderEntityType($entity_type)) {
      return;
    }

    $displays = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties([
      'targetEntityType' => $entity_type_id,
      'bundle' => $entity->bundle(),
    ]);

    $instances = [];

    foreach ($displays as $display) {
      // @phpstan-ignore-next-line
      if (!$display->getDisplayBuilderOverrideField()) {
        continue;
      }
      // Through the plugin rather than by composing the instance ID here: the
      // ID format is the plugin's business, and it already holds both halves.
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('entity_view_override', [
        'display' => $display,
        'entity' => $entity,
      ]);
      $instance = $buildable->getInstance();

      if ($instance) {
        $instances[] = $instance;
      }
    }

    if ($instances) {
      $this->entityTypeManager->getStorage('display_builder_instance')->delete($instances);
    }
  }

}
