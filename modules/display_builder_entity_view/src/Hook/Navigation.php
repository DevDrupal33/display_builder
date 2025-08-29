<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for Navigation module support.
 */
class Navigation {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   An associative array of entity type definitions.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    foreach ($entity_types as $entityTypeId => $entityType) {
      if (!$this->isDisplayBuilderEntityType($entityType)) {
        continue;
      }

      $viewModeIds = $this->getEntityTypeViewModesIds($entityTypeId);

      if (!$viewModeIds) {
        continue;
      }

      $canonical = $entityType->getLinkTemplate('canonical');

      foreach ($viewModeIds as $viewModeId) {
        $entityType->setLinkTemplate("display_builder_override.{$viewModeId}", \sprintf('%s/display/%s', $canonical, $viewModeId));
      }
    }
  }

  /**
   * Determines if a given entity type is display builder relevant or not.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return bool
   *   Whether this entity type is a display builder candidate or not.
   *
   * @see \Drupal\navigation\EntityRouteHelper::isLayoutBuilderEntityType()
   * @see \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage::getEntityTypes()
   */
  protected function isDisplayBuilderEntityType(EntityTypeInterface $entityType): bool {
    return $entityType->entityClassImplements(FieldableEntityInterface::class)
      && $entityType->hasViewBuilderClass()
      && $entityType->hasLinkTemplate('canonical');
  }

  /**
   * Get the view mode machine name for this entity type.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return array
   *   The list of view mode IDs.
   */
  protected function getEntityTypeViewModesIds(string $entityTypeId): array {
    $viewModeIds = [];
    $viewModesList = $this->configFactory->listAll("core.entity_view_mode.{$entityTypeId}.");

    if ($viewModesList) {
      $viewModes = $this->configFactory->loadMultiple($viewModesList);

      foreach ($viewModes as $viewMode => $viewModeConfig) {
        $viewModeIds[] = \str_replace("core.entity_view_mode.{$entityTypeId}.", '', $viewMode);
      }
    }

    return $viewModeIds;
  }

}
