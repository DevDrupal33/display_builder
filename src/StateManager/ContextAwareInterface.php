<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

/**
 * Provides an interface for context-aware.
 */
interface ContextAwareInterface {

  /**
   * Gets the values for all defined contexts.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]|null
   *   An array of set contexts, keyed by context name.
   */
  public function getContexts(string $builder_id): ?array;

  /**
   * Sets the context values for this display variant.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts, keyed by context name.
   */
  public function setContexts(string $builder_id, array $contexts): void;

}
