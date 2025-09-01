<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  keyboard_shortcuts: [
    'o' => new TranslatableMarkup('Show logs view'),
  ],
  icon: 'list-columns-reverse',
)]
class LogsPanel extends IslandPluginBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->dateFormatter = $container->get('date.formatter');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $load = $builder->toArray();

    if (!$load) {
      return [];
    }

    $saveHash = $load['save']->hash ?? NULL;
    /** @var \Drupal\display_builder\HistoryStep $present */
    $present = $load['present'];

    if (!$present) {
      return [];
    }

    /** @var \Drupal\display_builder\HistoryStep[] $past */
    $past = $load['past'];
    /** @var \Drupal\display_builder\HistoryStep[] $future */
    $future = $load['future'];

    $build = [];
    $table_logs = [
      '#theme' => 'table',
      '#header' => [
        ['data' => $this->t('Step')],
        ['data' => $this->t('Saved')],
        ['data' => $this->t('Time')],
        ['data' => $this->t('User')],
        ['data' => $this->t('Message')],
      ],
      '#rows' => [],
    ];

    $saveInPresent = ($saveHash && $present->hash === $saveHash);
    $saveInPast = FALSE;
    $saveInFuture = FALSE;

    $table_logs['#rows'] = $this->buildRows($past, $present, $future, $saveHash, $saveInPast, $saveInPresent, $saveInFuture);
    $build[] = $table_logs;

    if ($saveHash && !$saveInFuture && !$saveInPresent && !$saveInPast) {
      $time = DisplayBuilderHelpers::formatTime($this->dateFormatter, $load['save']->time);
      $params = ['%hash' => $saveHash, '%time' => $time];
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Saved version not in history: %hash at %time', $params),
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
  public function onUpdate(string $builder_id, string $instance_id, ?string $current_island_id): array {
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
  protected function buildRows(array $past, ?HistoryStep $present, array $future, ?int $saveHash, bool &$saveInPast, bool &$saveInPresent, bool &$saveInFuture): array {
    $rows_past = [];
    $rows_present = [];
    $rows_future = [];

    foreach ($future as $index => $step) {
      if ($saveHash && $step->hash === $saveHash && !$saveInPresent) {
        $saveInFuture = TRUE;
      }
      $rows_future[] = $this->buildRow($index + 1, $step);
    }

    foreach ($past as $index => $step) {
      if ($saveHash && isset($step->hash) && $step->hash === $saveHash) {
        $saveInPast = TRUE;
      }

      if ($step) {
        $rows_past[] = $this->buildRow(-\count($past) + $index, $step);
      }
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
        $savedRow['data'][1] = '✅';
      }
    }

    return \array_merge($rows_past, $rows_present, $rows_future);
  }

  /**
   * Build a single row for the logs table.
   *
   * @param int $index
   *   The row index.
   * @param \Drupal\display_builder\HistoryStep $step
   *   The step data containing time and log message.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  private function buildRow(int $index, HistoryStep $step): array {
    $user = !empty($step->user) ? $this->entityTypeManager->getStorage('user')->load($step->user) : NULL;

    return [
      'hash' => $step->hash ?? '',
      'data' => [
        (string) $index,
        '',
        $step->time ? DisplayBuilderHelpers::formatTime($this->dateFormatter, $step->time) : NULL,
        $user ? $user->getDisplayName() : NULL,
        $step->log ?? '',
      ],
      'style' => ($index === 0) ? 'font-weight: bold;' : '',
    ];
  }

}
