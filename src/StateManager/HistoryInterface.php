<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

use Drupal\Component\Render\FormattableMarkup;

/**
 * History interface.
 *
 * When you implement Undo and Redo, you want to keep track of the history
 * of this state at different points in time.
 *
 * Inspired from https://redux.js.org/usage/implementing-undo-history
 */
interface HistoryInterface {

  /**
   * Get current step.
   *
   * The array has a state, a log and a time.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array
   *   The current data.
   */
  public function getCurrent(string $builder_id): array;

  /**
   * Get current step's state.
   *
   * The array is the state root with a list of slot sources.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array
   *   The current state.
   */
  public function getCurrentState(string $builder_id): array;

  /**
   * Get futures steps.
   *
   * The array is a list of steps. Each step as a state, a log and a time.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array
   *   The list of future steps.
   */
  public function getFuture(string $builder_id): array;

  /**
   * Get futures steps.
   *
   * The array is a list of steps. Each step as a state, a log and a time.
   *
   * @param string $builder_id
   *   The display builder id.
   *
   * @return array
   *   The list of past steps.
   */
  public function getPast(string $builder_id): array;

  /**
   * Empty past, empty future, set present.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param string $entity_config_id
   *   The display builder entity config id.
   * @param array $state
   *   The state to set.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The contexts for this builder_id.
   */
  public function init(string $builder_id, string $entity_config_id, array $state, array $contexts): void;

  /**
   * Redo handler.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function redo(string $builder_id): void;

  /**
   * Set a new present.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $state
   *   The state to set.
   * @param string|\Drupal\Component\Render\FormattableMarkup $log_message
   *   (Optional) The log message.
   * @param bool $check_hash
   *   (Optional) Should check hash to avoid duplicates. Default to TRUE.
   */
  public function setNewPresent(string $builder_id, array $state, FormattableMarkup|string $log_message = '', bool $check_hash = TRUE): void;

  /**
   * Undo handler.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function undo(string $builder_id): void;

  /**
   * Clear handler.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function clear(string $builder_id): void;

  /**
   * Restore handler.
   *
   * @param string $builder_id
   *   The display builder id.
   */
  public function restore(string $builder_id): void;

}
