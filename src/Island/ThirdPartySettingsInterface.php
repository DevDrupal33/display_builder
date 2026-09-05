<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

/**
 * Interface for island plugins providing third party settings.
 */
interface ThirdPartySettingsInterface {

  /**
   * Get settings summary items.
   *
   * Plain items, no markup: what to do with them is the caller's business.
   *
   * @return array<string|\Stringable>
   *   The summary items, empty when there is nothing to say.
   *
   * @see \Drupal\display_builder\SummaryCollector
   */
  public function getSummary(): array;

}
