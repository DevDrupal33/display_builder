<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_page_layout\Plugin\DisplayBuildable\PageLayout;

/**
 * Hook implementations for the display_builder_page_layout module.
 */
class PageLayoutHook {

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

    if (\str_starts_with($id, PageLayout::getPrefix())) {
      $operations['build'] = [
        'title' => new TranslatableMarkup('Build display'),
        'url' => PageLayout::getUrlFromInstanceId($id),
        'weight' => -1,
      ];
      $operations['edit'] = [
        'title' => new TranslatableMarkup('Edit display'),
        'url' => PageLayout::getDisplayUrlFromInstanceId($id),
        'weight' => 10,
      ];
    }
  }

}
