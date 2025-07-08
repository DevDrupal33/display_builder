<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplayStorage;
use Drupal\display_builder_entity_view\Form\DisplayBuilderEntityViewDisplayForm;
use Drupal\layout_builder\Form\DefaultsEntityForm;

/**
 * Hook implementations for display_builder.
 */
class DisplayBuilderEntityViewHook {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['entity_view_display']->setClass(DisplayBuilderEntityViewDisplay::class)->setStorageClass(DisplayBuilderEntityViewDisplayStorage::class)->setFormClass('display_builder', DefaultsEntityForm::class)->setFormClass('edit', DisplayBuilderEntityViewDisplayForm::class);
  }

  /**
   * Implements hook_entity_view_alter().
   *
   * ExtraFieldBlock block plugins add placeholders for each extra field which
   * is configured to be displayed. Those placeholders are replaced by this hook
   * Modules that implement hook_entity_extra_field_info() use their
   * implementations of hook_entity_view_alter() to add the rendered output of
   * the extra fields they provide, so we cannot get the rendered output
   * of extra fields before this point in the view process.
   * display_builder_module_implements_alter() moves this implementation of
   * hook_entity_view_alter() to the end of the list.
   *
   * @see \Drupal\display_builder\Plugin\Block\ExtraFieldBlock::build()
   * @see display_builder_module_implements_alter()
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    // Only replace extra fields when Layout Builder has been used to alter the
    // build. @see \Drupal\display_builder\Entity\DisplayBuilderEntityViewDisplay::buildMultiple().
    if (isset($build['_display_builder']) && !Element::isEmpty($build['_display_builder'])) {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
      $field_manager = \Drupal::service('entity_field.manager');
      $extra_fields = $field_manager->getExtraFields($entity->getEntityTypeId(), $entity->bundle());

      if (!empty($extra_fields['display'])) {
        foreach (array_keys($extra_fields['display']) as $field_name) {
          // @todo something better other than removing extra field
          unset($build[$field_name]);
        }
      }
    }
    $route_name = \Drupal::routeMatch()->getRouteName();

    // If the entity is displayed within a Layout Builder block and the current
    // route is in the Layout Builder UI, then remove all contextual link
    // placeholders.
    if ($route_name && $display instanceof DisplayBuilderEntityViewDisplay && str_starts_with($route_name, 'display_builder.')) {
      unset($build['#contextual_links']);
    }
  }

}
