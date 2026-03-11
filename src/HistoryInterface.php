<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\display_builder\Plugin\Field\FieldType\HistoryStep;

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
   * @return \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep|null
   *   The current data.
   */
  public function getCurrent(): ?HistoryStep;

  /**
   * Get the state of the current step.
   *
   * @return array
   *   The current state.
   */
  public function getCurrentState(): array;

  /**
   * Set a new present.
   *
   * @param array $state
   *   The state to set.
   * @param string|\Drupal\Component\Render\FormattableMarkup $log_message
   *   (Optional) The log message.
   * @param bool $check_hash
   *   (Optional) Should check hash to avoid duplicates. Default to TRUE.
   */
  public function setNewPresent(array $state, FormattableMarkup|string $log_message = '', bool $check_hash = TRUE): void;

  /**
   * Get number of past logs.
   *
   * @return int
   *   The number of past logs.
   */
  public function getCountPast(): int;

  /**
   * Get number of future logs.
   *
   * @return int
   *   The number of future logs.
   */
  public function getCountFuture(): int;

  /**
   * Move history to the last past state.
   */
  public function undo(): void;

  /**
   * Move history to the first future state.
   */
  public function redo(): void;

  /**
   * Reset history to the current state.
   */
  public function clear(): void;

}
