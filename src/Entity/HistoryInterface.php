<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

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
   * Set a new present.
   *
   * @param array $state
   *   The state to set.
   * @param string|\Stringable $log_message
   *   (Optional) The log message.
   * @param bool $check_hash
   *   (Optional) Should check hash to avoid duplicates. Default to TRUE.
   * @param bool $index
   *   (Optional) When TRUE the raw $state is normalized through SourceTree
   *   before storing. Set to FALSE when the data was already produced by
   *   SourceTree::getTree() to skip redundant normalization. Default to TRUE.
   */
  public function setNewPresent(array $state, string|\Stringable $log_message = '', bool $check_hash = TRUE, bool $index = TRUE): void;

  /**
   * Get the past steps.
   *
   * @return array
   *   The past steps.
   */
  public function getPast(): array;

  /**
   * Get the future steps.
   *
   * @return array
   *   The future steps.
   */
  public function getFuture(): array;

}
