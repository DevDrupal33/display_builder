<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
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
   * Implements hook_entity_field_type_alter().
   *
   * @param array $info
   *   The field types to alter.
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    $info['ui_patterns_source']['list_class'] = DisplayBuilderItemList::class;
  }

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

    if (\str_starts_with($id, EntityViewDisplay::getPrefix())) {
      $operations['build'] = [
        'title' => new TranslatableMarkup('Build display'),
        'url' => EntityViewDisplay::getUrlFromInstanceId($id),
        'weight' => -1,
      ];
      $operations['edit'] = [
        'title' => new TranslatableMarkup('Edit display'),
        'url' => EntityViewDisplay::getDisplayUrlFromInstanceId($id),
        'weight' => 10,
      ];
    }
    elseif (\str_starts_with($id, DisplayBuilderItemList::getPrefix())) {
      $operations['build'] = [
        'title' => new TranslatableMarkup('Build display'),
        'url' => DisplayBuilderItemList::getUrlFromInstanceId($id),
        'weight' => -1,
      ];
      $operations['edit'] = [
        'title' => new TranslatableMarkup('Edit display'),
        'url' => DisplayBuilderItemList::getUrlFromInstanceId($id),
        'weight' => 10,
      ];
    }
  }

  /**
   * Implements hook_display_builder_provider_info().
   *
   * @return array
   *   An associative array of display builder providers.
   */
  #[Hook('display_builder_provider_info')]
  public function displayBuilderProviderInfo(): array {
    return [
      'entity_view' => [
        'label' => new TranslatableMarkup('Entity view'),
        'class' => EntityViewDisplay::class,
        'prefix' => EntityViewDisplay::getPrefix(),
      ],
      'entity_view_override' => [
        'label' => new TranslatableMarkup('Entity view override'),
        'class' => DisplayBuilderItemList::class,
        'prefix' => DisplayBuilderItemList::getPrefix(),
      ],
    ];
  }

}
