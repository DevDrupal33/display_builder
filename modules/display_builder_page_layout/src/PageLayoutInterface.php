<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\display_builder\DisplayBuildableInterface;

/**
 * Provides an interface defining a page layout entity type.
 */
interface PageLayoutInterface extends ConfigEntityInterface, DisplayBuildableInterface, EntityWithPluginCollectionInterface {

  /**
   * Get conditions plugins.
   *
   * @return \Drupal\Core\Condition\ConditionPluginCollection
   *   A collection of conditions plugins attached to the page layout.
   */
  public function getConditions(): ConditionPluginCollection;

}
