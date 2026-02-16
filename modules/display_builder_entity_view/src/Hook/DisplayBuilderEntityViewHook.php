<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\Form\EntityViewDisplayForm;
use Drupal\display_builder_entity_view\Form\LayoutBuilderEntityViewDisplayForm;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityView;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;

/**
 * Hook implementations for display_builder_entity_view.
 */
class DisplayBuilderEntityViewHook {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
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
   * Implements hook_entity_operation_alter().
   *
   * @param array $operations
   *   An associative array of operations.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the operations are being altered.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if (!$entity instanceof InstanceInterface) {
      return;
    }

    $id = (string) $entity->id();

    if (\str_starts_with($id, EntityView::getPrefix())) {
      $operations['build'] = [
        'title' => new TranslatableMarkup('Build display'),
        'url' => EntityView::getUrlFromInstanceId($id),
        'weight' => -1,
      ];
      $operations['edit'] = [
        'title' => new TranslatableMarkup('Edit display'),
        'url' => EntityView::getDisplayUrlFromInstanceId($id),
        'weight' => 10,
      ];
    }
    elseif (\str_starts_with($id, EntityViewOverride::getPrefix())) {
      $operations['build'] = [
        'title' => new TranslatableMarkup('Build display'),
        'url' => EntityViewOverride::getUrlFromInstanceId($id),
        'weight' => -1,
      ];
      $operations['edit'] = [
        'title' => new TranslatableMarkup('Edit display'),
        'url' => EntityViewOverride::getUrlFromInstanceId($id),
        'weight' => 10,
      ];
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

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');

    foreach ($displays as $display) {
      // @phpstan-ignore-next-line
      if (!$display->getDisplayBuilderOverrideField()) {
        continue;
      }
      $field_name = $display->getThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY);

      $instance_id = \sprintf(
        '%s%s__%s__%s',
        EntityViewOverride::getPrefix(),
        $entity_type_id,
        $entity->id(),
        $field_name,
      );

      $instance = $storage->load($instance_id);

      if (!$instance) {
        return;
      }
      $storage->delete([$instance]);
    }
  }

}
