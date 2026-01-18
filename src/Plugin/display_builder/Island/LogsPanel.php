<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\HistoryStep;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Logs island plugin implementation.
 */
#[Island(
  id: 'logs',
  label: new TranslatableMarkup('Logs'),
  description: new TranslatableMarkup('Logs based on changes history.'),
  type: IslandType::View,
  default_region: 'main',
  icon: 'list-columns-reverse',
)]
class LogsPanel extends IslandPluginBase {

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'o' => t('Show the logs'),
    ];
  }

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
    $table = [
      '#theme' => 'table',
      '#header' => [
        ['data' => $this->t('Step')],
        ['data' => $this->t('Published')],
        ['data' => $this->t('Time')],
        ['data' => $this->t('User')],
        ['data' => $this->t('Message')],
      ],
      '#rows' => $rows,
    ];

    return [
      $table,
      $save ? $this->printSaveAlert(\array_merge($load['past'], [$present], $load['future']), $save) : [],
    ];
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
      'hash' => $step->hash,
      'data' => [
        (string) $index,
        ($save && $step->hash === $save->hash) ? '✅' : '',
        $step->time ? DisplayBuilderHelpers::formatTime($this->dateFormatter, $step->time) : NULL,
        $user ? $user->getDisplayName() : NULL,
        $step->log ?? '',
      ],
      'style' => ($index === 0) ? 'font-weight: bold;' : '',
    ];
  }

  /**
   * Print an alert if the saved step is not in the history.
   *
   * @param \Drupal\display_builder\HistoryStep[] $steps
   *   All steps: past, present and future.
   * @param \Drupal\display_builder\HistoryStep $save
   *   Saved state.
   *
   * @return array
   *   A renderable array.
   */
  private function printSaveAlert(array $steps, HistoryStep $save): array {
    $save_found = FALSE;

    foreach ($steps as $step) {
      if ($step && $step->hash === $save->hash) {
        $save_found = TRUE;

        break;
      }
    }

    if ($save_found) {
      return [];
    }
    $params = [
      '%time' => DisplayBuilderHelpers::formatTime($this->dateFormatter, $save->time),
    ];

    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Saved at %time but not visible in logs', $params),
    ];
  }

}
