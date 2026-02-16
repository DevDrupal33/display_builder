<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\display_builder\ProfileInterface;

/**
 * Provides an interface defining a page layout entity type.
 */
interface PageLayoutInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Get conditions plugins.
   *
   * @return \Drupal\Core\Condition\ConditionPluginCollection
   *   A collection of conditions plugins attached to the page layout.
   */
  public function getConditions(): ConditionPluginCollection;

  /**
   * Get display builder profile config entity.
   *
   * If NULL, the Display Builder is not activated for this entity.
   *
   * @return ?ProfileInterface
   *   The display builder profile config entity.
   */
  public function getProfile(): ?ProfileInterface;

  /**
   * Get sources tree.
   *
   * @return array
   *   A list of nestable sources.
   */
  public function getSources(): array;

  /**
   * Save sources tree retrieved from the Instance entity to config or content.
   *
   * Triggered by a DisplayBuilderEvents::ON_SAVE event.
   *
   * @param array $sources
   *   A list of nestable sources.
   */
  public function setSources(array $sources): void;

}
