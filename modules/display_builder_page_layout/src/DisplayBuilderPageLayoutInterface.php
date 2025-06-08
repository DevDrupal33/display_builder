<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

/**
 * Interface for managing Display Builder Page Layout configuration.
 */
interface DisplayBuilderPageLayoutInterface {

  /**
   * Delete the page layout configuration and display builder instance related.
   */
  public function delete(): void;

  /**
   * Create a configuration and display builder instance for page layout.
   *
   * @return string
   *   The new builder id created.
   */
  public function newPageLayout(): string;

  /**
   * Save the page layout configuration with current state of display builder.
   *
   * @param string $builder_id
   *   The builder id, default to the id in config.
   */
  public function savePageLayoutCurrentState(string $builder_id): void;

  /**
   * Save the page manager configuration with current state of display builder.
   *
   * @param string $builder_id
   *   The builder id, default to the id in config.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts, keyed by context name.
   */
  public function savePageManagerCurrentState(string $builder_id, array $contexts): void;

}
