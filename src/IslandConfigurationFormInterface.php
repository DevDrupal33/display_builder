<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for island plugins with a configuration form (for profile).
 */
interface IslandConfigurationFormInterface extends PluginFormInterface {

  /**
   * Returns a short summary for the current configuration.
   *
   * The configuration is managed by the ConfigurableInterface implementation.
   *
   * @return array<string|\Stringable>
   *   A short summary of the configuration.
   */
  public function configurationSummary(): array;

}
