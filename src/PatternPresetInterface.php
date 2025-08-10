<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Pattern preset entity type.
 */
interface PatternPresetInterface extends ConfigEntityInterface {

  /**
   * Return the ready to use sources.
   *
   * This is not the same as WithDisplayBuilderInterface::getSources() because
   * the root level is a single nestable source plugin instead of a list.
   *
   * @param array $contexts
   *   (Optional) Contexts for the sources.
   * @param bool $fillInstanceId
   *   (Optional) Set instance_id on all children. Default to TRUE.
   *
   * @return array
   *   The preset data.
   */
  public function getSources(array $contexts = [], bool $fillInstanceId = TRUE): array;

}
