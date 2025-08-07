<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view;

/**
 * Provides method to know if Display Builder is enabled.
 */
interface DisplayBuilderEnabledInterface {

  /**
   * Determines if Display Builder is enabled.
   *
   * @return bool
   *   TRUE if Display Builder is enabled, FALSE otherwise.
   */
  public function isDisplayBuilderEnabled();

}
