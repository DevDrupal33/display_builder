<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for island plugins providing third party settings.
 */
interface ThirdPartySettingsInterface {

  /**
   * Get settings summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The summary.
   */
  public function getSummary(): ?TranslatableMarkup;

}
