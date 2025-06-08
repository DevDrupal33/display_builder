<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Logs island plugin implementation.
 */
#[Island(
  id: 'logs',
  label: new TranslatableMarkup('Logs'),
  description: new TranslatableMarkup('Display Builder Logs based on history.'),
  type: IslandType::View,
  keyboard_shortcuts: [
    'o' => new TranslatableMarkup('Show logs view'),
  ],
  icon: 'list-columns-reverse',
)]
class LogsPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $load = $this->stateManager->load($builder_id);

    $saveHash = $load['save']['hash'] ?? NULL;
    $present = $load['present'];
    $past = $load['past'];
    $future = $load['future'];

    $build = [];
    $table_logs = [
      '#theme' => 'table',
      '#header' => [
        ['data' => $this->t('Id')],
        ['data' => $this->t('Step')],
        ['data' => $this->t('Saved')],
        ['data' => $this->t('Time')],
        ['data' => $this->t('Message')],
      ],
      '#rows' => [],
    ];

    $saveInPresent = ($saveHash && $present['hash'] === $saveHash);
    $saveInPast = FALSE;
    $saveInFuture = FALSE;

    $table_logs['#rows'] = $this->buildRows($past, $present, $future, $saveHash, $saveInPast, $saveInPresent, $saveInFuture);
    $build[] = $table_logs;

    if ($saveHash && !$saveInFuture && !$saveInPresent && !$saveInPast) {
      $params = ['%hash' => $saveHash, '%time' => $load['save']['time']];
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Saved version not in history: %hash at %time', $params),
      ];
    }

    return $build;
  }

  /**
   * Build rows for the logs table.
   *
   * @param array $past
   *   Steps with time and log message.
   * @param array $present
   *   A step with time and log message.
   * @param array $future
   *   Steps with time and log message.
   * @param int|null $saveHash
   *   Hash of the saved state.
   * @param bool $saveInPast
   *   Is the saved stated in the past?
   * @param bool $saveInPresent
   *   Is the saved stated in the present?
   * @param bool $saveInFuture
   *   Is the saved stated in the future?
   *
   * @return array
   *   A renderable array representing a table row.
   */
  protected function buildRows(array $past, array $present, array $future, int|NULL $saveHash, bool &$saveInPast, bool &$saveInPresent, bool &$saveInFuture): array {
    $rows_past = [];
    $rows_present = [];
    $rows_future = [];

    foreach ($future as $index => $step) {
      if ($saveHash && $step['hash'] === $saveHash && !$saveInPresent) {
        $saveInFuture = TRUE;
      }
      $rows_future[] = $this->buildRow($index + 1, $step);
    }

    foreach ($past as $index => $step) {
      if ($saveHash && $step['hash'] === $saveHash) {
        $saveInPast = TRUE;
      }
      $rows_past[] = $this->buildRow(-\count($past) + $index, $step);
    }

    // Present data.
    $rows_present[] = $this->buildRow(0, $present);

    // Process the saved mark.
    $savedRow = NULL;

    if ($saveHash) {
      if ($saveInPresent) {
        $savedRow = &$rows_present[0];
      }
      elseif ($saveInFuture) {
        foreach ($rows_future as &$row) {
          if ($row['hash'] === $saveHash) {
            $savedRow = &$row;

            // Get closest to present future.
            break;
          }
        }
      }
      elseif ($saveInPast) {
        foreach ($rows_past as &$row) {
          if ($row['hash'] === $saveHash) {
            $savedRow = &$row;
            // Do not break to get closest to present.
          }
        }
      }

      if ($savedRow !== NULL) {
        $savedRow['data'][2] = '✅';
      }
    }
    return \array_merge($rows_past, $rows_present, $rows_future);
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
   * Build a single row for the logs table.
   *
   * @param int $index
   *   The row index.
   * @param array $step
   *   The step data containing time and log message.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  private function buildRow(int $index, array $step): array {
    return [
      'hash' => $step['hash'] ?? '',
      'data' => [
        \sprintf('<small>%s</small>', $step['hash'] ?? ''),
        (string) $index,
        '',
        $step['time'] ?? NULL,
        $step['log'] ?? '',
      ],
      'style' => ($index === 0) ? 'font-weight: bold;' : '',
    ];
  }

}
