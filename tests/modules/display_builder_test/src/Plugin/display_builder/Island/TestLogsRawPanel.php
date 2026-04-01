<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\Plugin\Field\FieldType\HistoryStep;

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

  use IslandReloadEventsTrait;

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $present */
    $present = $builder->get('present')->first();

    if (!$present) {
      return [];
    }

    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $save */
    $save = $builder->get('save')->first() ?? NULL;
    $rows = $this->buildRows($builder->get('past'), $present, $builder->get('future'), $save);

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
  public function onPublish(InstanceInterface $instance): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * Build rows for the logs table.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $past
   *   Steps with time and log message.
   * @param \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $present
   *   A step with time and log message.
   * @param \Drupal\Core\Field\FieldItemListInterface $future
   *   Steps with time and log message.
   * @param \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $save
   *   Saved state.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  protected function buildRows(FieldItemListInterface $past, ?HistoryStep $present, FieldItemListInterface $future, ?HistoryStep $save): array {
    $rows = [];

    foreach ($past as $index => $step) {
      /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $step */
      $rows[] = $this->buildRow(-\count($past) + $index, $step, $save);
    }

    // Present data.
    $rows[] = $this->buildRow(0, $present, $save);

    foreach ($future as $index => $step) {
      /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $step */
      $rows[] = $this->buildRow($index + 1, $step, $save);
    }

    return $rows;
  }

  /**
   * Build a single row for the logs table.
   *
   * @param int $index
   *   The row index.
   * @param \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $step
   *   The step data containing time and log message.
   * @param \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $save
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
