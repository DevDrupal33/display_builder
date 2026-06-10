<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;

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
    $published_hash = $builder->getPublishedHash();
    $rows = $this->buildRows($builder, $published_hash);

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
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The instance entity.
   * @param ?int $published_hash
   *   The hash of the published content, if any.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  protected function buildRows(InstanceInterface $builder, ?int $published_hash): array {
    $rows = [];
    $past = $builder->getPast();

    foreach ($past as $index => $step) {
      /** @var \Drupal\display_builder\InstanceInterface $step */
      $rows[] = $this->buildRow(-\count($past) + $index, $step, $published_hash);
    }

    // Present data.
    $rows[] = $this->buildRow(0, $builder, $published_hash);

    foreach ($builder->getFuture() as $index => $step) {
      /** @var \Drupal\display_builder\InstanceInterface $step */
      $rows[] = $this->buildRow($index + 1, $step, $published_hash);
    }

    return $rows;
  }

  /**
   * Build a single row for the logs table.
   *
   * @param int $index
   *   The row index.
   * @param \Drupal\display_builder\InstanceInterface $step
   *   The step data containing time and log message.
   * @param ?int $published_hash
   *   The hash of the published content, if any.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  private function buildRow(int $index, InstanceInterface $step, ?int $published_hash): array {
    $hash = $step->getHash();

    return [
      (string) $index,
      ($published_hash && $hash === $published_hash) ? '_SAVED_' : '_NOT_SAVED_',
      (string) $step->time ?? '_NO_TIME_',
      $step->getRevisionUser()?->getDisplayName() ?? '_NO_USER_',
      (string) $step->log ?? '_NO_LOG_',
    ];
  }

}
