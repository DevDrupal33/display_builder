<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for island plugins providing third party settings.
 */
interface ThirdPartySettingsInterface {

  /**
   * Get settings summary renderable.
   *
   * @return array|null
   *   The renderable summary including translations.
   */
  public function getSummary(): ?array;

}
