<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Names the bundle and view mode an entity view display is made of.
 *
 * Both halves of ::getDisplayLabel() for the two entity view buildables, and
 * only for those: an entity view display is the one kind of display named
 * after a bundle and a view mode. A page layout is named after itself, and a
 * Views display after its view, so neither has any use for this.
 *
 * Only usable on a DisplayBuildablePluginBase subclass: both methods read the
 * services listed below, which the using class must assign in its ::create().
 *
 * @see \Drupal\display_builder\DisplayBuildableInterface::getDisplayLabel()
 */
trait EntityDisplayLabelTrait {

  /**
   * The entity type bundle info.
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * The entity display repository.
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * Get the human name of a view mode.
   *
   * Through the repository rather than the entity_view_mode storage: it is one
   * cached array per entity type instead of one config entity load per call,
   * it already names the 'default' mode that has no config entity of its own,
   * and it is what hook_entity_view_mode_info_alter() gets to change.
   *
   * @param string $entity_type_id
   *   The entity type the view mode belongs to.
   * @param string $mode
   *   The view mode machine name.
   *
   * @return string
   *   The view mode name, or the machine name when the mode is unknown.
   */
  protected function getViewModeLabel(string $entity_type_id, string $mode): string {
    $options = $this->entityDisplayRepository->getViewModeOptions($entity_type_id);

    return (string) ($options[$mode] ?? $mode);
  }

  /**
   * Get the human name of a bundle.
   *
   * @param string $entity_type_id
   *   The entity type the bundle belongs to.
   * @param string $bundle
   *   The bundle machine name.
   *
   * @return string
   *   The bundle name, or the machine name when the bundle is unknown.
   */
  protected function getBundleLabel(string $entity_type_id, string $bundle): string {
    $info = $this->bundleInfo->getBundleInfo($entity_type_id);

    return (string) ($info[$bundle]['label'] ?? $bundle);
  }

}
