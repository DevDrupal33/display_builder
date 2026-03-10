<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HistoryStep;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Logs island plugin implementation.
 */
#[Island(
  id: 'test_logs_raw',
  label: new TranslatableMarkup('[Test] Logs raw'),
  description: new TranslatableMarkup('Logs raw for tests based on changes history.'),
  type: IslandType::View,
)]
class TestLogsRawPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $load = $builder->toArray();

    if (!$load) {
      return [];
    }

    /** @var \Drupal\display_builder\HistoryStep $present */
    $present = $load['present'];

    if (!$present) {
      return [];
    }

    $save = $load['save'] ?? NULL;
    $rows = $this->buildRows($load['past'], $present, $load['future'], $save);

    $build = [];

    foreach ($rows as $row) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['log-row'],
        ],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => \implode(' | ', $row),
          '#attributes' => [
            'class' => ['test-result-log-row'],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onSave(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * Build rows for the logs table.
   *
   * @param \Drupal\display_builder\HistoryStep[] $past
   *   Steps with time and log message.
   * @param \Drupal\display_builder\HistoryStep $present
   *   A step with time and log message.
   * @param \Drupal\display_builder\HistoryStep[] $future
   *   Steps with time and log message.
   * @param \Drupal\display_builder\HistoryStep $save
   *   Saved state.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  protected function buildRows(array $past, ?HistoryStep $present, array $future, ?HistoryStep $save): array {
    $rows = [];

    foreach (\array_filter($past) as $index => $step) {
      $rows[] = $this->buildRow(-\count($past) + $index, $step, $save);
    }

    // Present data.
    $rows[] = $this->buildRow(0, $present, $save);

    foreach (\array_filter($future) as $index => $step) {
      $rows[] = $this->buildRow($index + 1, $step, $save);
    }

    return $rows;
  }

  /**
   * Build a single row for the logs table.
   *
   * @param int $index
   *   The row index.
   * @param \Drupal\display_builder\HistoryStep $step
   *   The step data containing time and log message.
   * @param \Drupal\display_builder\HistoryStep $save
   *   Saved state.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  private function buildRow(int $index, HistoryStep $step, ?HistoryStep $save): array {
    $user = !empty($step->user) ? $this->entityTypeManager->getStorage('user')->load($step->user) : NULL;

    return [
      (string) $index,
      ($save && (string) $step->hash === $save->hash) ? '_SAVED_' : '_NOT_SAVED_',
      (string) $step->time ?? '_NO_TIME_',
      $user ? $user->getDisplayName() : '_NO_USER_',
      (string) $step->log ?? '_NO_LOG_',
    ];
  }

}
