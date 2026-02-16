<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * The Display Builder Instance storage interface.
 */
interface InstanceStorageInterface extends EntityStorageInterface {

  /**
   * Create an instance from implementation.
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $implementation
   *   The implementation.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function createFromImplementation(DisplayBuildableInterface $implementation): EntityInterface;

}
