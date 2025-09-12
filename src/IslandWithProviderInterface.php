<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for island plugins with a provider management.
 */
interface IslandWithProviderInterface {

  public const PROVIDER_EXCLUDE = [];

  /**
   * Get the plugin definitions.
   *
   * @return array
   *
   *   The plugin definitions.
   */
  public function getProviderDefinitions(): array;

}
