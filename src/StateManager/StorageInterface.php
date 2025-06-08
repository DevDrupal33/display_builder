<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

/**
 * Display storage interface.
 *
 * To load and save display builded from state until integration with Drupal
 * displays.
 */
interface StorageInterface extends ContextAwareInterface, DataSateInterface, HistoryInterface {

  /**
   * Get the entity config id.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return string
   *   The entity config id.
   */
  public function getEntityConfigId(string $builder_id): string;

  /**
   * Set the entity config id.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $entity_config_id
   *   The display builder config id.
   */
  public function setEntityConfigId(string $builder_id, string $entity_config_id): void;

  /**
   * Save a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $entity_config_id
   *   The entity config id.
   * @param array $builder_data
   *   The display builder data.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The contexts.
   */
  public function init(string $builder_id, string $entity_config_id, array $builder_data, array $contexts): void;

  /**
   * Save a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $builder_data
   *   The display builder data.
   */
  public function saveData(string $builder_id, array $builder_data): void;

}
