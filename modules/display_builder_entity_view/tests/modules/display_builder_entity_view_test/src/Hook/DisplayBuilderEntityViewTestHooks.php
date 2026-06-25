<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view_test\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for display_builder_entity_view_test.
 */
class DisplayBuilderEntityViewTestHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra = [];
    $extra['node']['display_builder_test']['display'] = [
      'db_test_extra_field' => [
        'label' => $this->t('DB Test Extra Field'),
        'description' => $this->t('A pseudo-field component.'),
        'weight' => 0,
        'visible' => TRUE,
      ],
      'db_test_entity_type_field' => [
        'label' => $this->t('DB Test Entity Type Field'),
        'description' => $this->t('Extra field rendered by hook_ENTITY_TYPE_view.'),
        'weight' => 0,
        'visible' => TRUE,
      ],
    ];

    return $extra;
  }

  /**
   * Implements hook_entity_view().
   *
   * Renders db_test_extra_field for node display_builder_test entities.
   *
   * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
   */
  #[Hook('entity_view')]
  public static function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() !== 'node' && $entity->bundle() !== 'display_builder_test') {
      return;
    }

    if ($display->getComponent('db_test_extra_field')) {
      $build['db_test_extra_field'] = [
        '#markup' => 'hook_entity_view:db_test_extra_field:' . $entity->label(),
      ];
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   *
   * Renders db_test_entity_type_field using the entity-type-specific hook.
   *
   * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
   */
  #[Hook('node_view')]
  public static function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->bundle() !== 'display_builder_test') {
      return;
    }

    if ($display->getComponent('db_test_entity_type_field')) {
      $build['db_test_entity_type_field'] = [
        '#markup' => 'hook_entity_test_view:db_test_entity_type_field:' . $entity->label(),
      ];
    }
  }

}
