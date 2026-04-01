<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\Plugin\Field\FieldType\HistoryStep;
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

  use IslandReloadEventsTrait;

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
      'key' => 'o',
      'help' => t('Show the logs'),
    ];
  }

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
    $rows = $this->buildRows($builder);
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
      $save ? $this->printSaveAlert(\array_merge($builder->get('past')->getValue(), [$present->getValue()], $builder->get('future')->getValue()), $save) : [],
    ];
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
   *   A step with time and log message.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  protected function buildRows(InstanceInterface $builder): array {
    $rows = [];
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $save */
    $save = $builder->get('save')->first();
    $past = $builder->getPast();

    foreach ($past as $index => $step) {
      /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $step */
      $rows[] = $this->buildRow(-\count($past) + $index, $step, $save);
    }

    // Present data.
    $rows[] = $this->buildRow(0, $builder->getCurrent(), $save);

    foreach ($builder->getFuture() as $index => $step) {
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
    $user = !empty($step->getUser()) ? $this->entityTypeManager->getStorage('user')->load($step->getUser()) : NULL;

    return [
      'hash' => $step->getHash(),
      'data' => [
        (string) $index,
        ($save && $step->getHash() === $save->getHash()) ? '✅' : '',
        $step->getTime() ? DisplayBuilderHelpers::formatTime($this->dateFormatter, $step->getTime()) : NULL,
        $user ? $user->getDisplayName() : NULL,
        $step->getLog() ?? '',
      ],
      'style' => ($index === 0) ? 'font-weight: bold;' : '',
    ];
  }

  /**
   * Print an alert if the saved step is not in the history.
   *
   * @param array $steps
   *   All steps: past, present and future.
   * @param \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $save
   *   Saved state.
   *
   * @return array
   *   A renderable array.
   */
  private function printSaveAlert(array $steps, HistoryStep $save): array {
    foreach ($steps as $step) {
      if ($step && ($step['hash'] === $save->getHash())) {
        return [];
      }
    }

    $params = [
      '%time' => DisplayBuilderHelpers::formatTime($this->dateFormatter, $save->getTime()),
    ];

    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Saved at %time but not visible in logs', $params),
    ];
  }

}
