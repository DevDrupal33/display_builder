<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_entity_form\Entity\EntityFormDisplay;
use Drupal\display_builder_entity_form\Form\EntityFormDisplayForm;

/**
 * Hook implementations for display_builder_entity_form.
 */
class DisplayBuilderEntityFormHook {

  /**
   * Implements hook_entity_type_alter().
   *
   * @param array $entity_types
   *   An associative array of entity type definitions.
   */
  #[Hook('entity_type_alter', order: new OrderAfter(['layout_builder']))]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['entity_form_display']
      ->setClass(EntityFormDisplay::class)
      ->setFormClass('edit', EntityFormDisplayForm::class);
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

    if (!EntityFormDisplay::checkInstanceId($id)) {
      return;
    }
    $operations['build'] = [
      'title' => new TranslatableMarkup('Build display'),
      'url' => EntityFormDisplay::getUrlFromInstanceId($id),
      'weight' => -1,
    ];
    $operations['edit'] = [
      'title' => new TranslatableMarkup('Edit display'),
      'url' => EntityFormDisplay::getDisplayUrlFromInstanceId($id),
      'weight' => 10,
    ];
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
      'entity_form' => [
        'label' => new TranslatableMarkup('Entity form'),
        'class' => EntityFormDisplay::class,
        'prefix' => EntityFormDisplay::getPrefix(),
      ],
    ];
  }

}
