<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

/**
 * Provides an interface for save-aware.
 *
 * @todo probably not needed as we should be able to save all display.
 */
interface SaveContextInterface {

  /**
   * Check display has required context, meaning it can save value.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $contexts
   *   (Optional) contexts if already accessible.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function canSaveContextsRequirement(string $builder_id, ?array $contexts = NULL): bool;

  /**
   * Check display has required context, meaning it can save value.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $key
   *   The context key to look for.
   * @param array $contexts
   *   (Optional) contexts if already accessible.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function hasSaveContextsRequirement(string $builder_id, string $key, ?array $contexts = NULL): bool;

}
