<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for island plugins with a form.
 */
interface IslandWithFormInterface {

  /**
   * Get The form class for this Island.
   *
   * @return string
   *   The Class string
   */
  public static function getFormClass(): string;

}
