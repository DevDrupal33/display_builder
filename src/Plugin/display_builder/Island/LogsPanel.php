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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Logs island plugin implementation.
 */
#[Island(
  id: 'logs',
  label: new TranslatableMarkup('Logs'),
  description: new TranslatableMarkup('Logs based on changes history.'),
  type: IslandType::View,
  region: 'main',
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
      'help' => \t('Show the logs'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $published_hash = $builder->getPublishedHash();

    $table = [
      '#theme' => 'table',
      '#header' => [
        ['data' => $this->t('Step')],
        ['data' => $this->t('Published')],
        ['data' => $this->t('Time')],
        ['data' => $this->t('User')],
        ['data' => $this->t('Message')],
      ],
      '#rows' => $this->buildRows($builder, $published_hash),
    ];

    return [
      $table,
      $published_hash ? $this->printSaveAlert($builder, $published_hash) : [],
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
   *   The instance entity.
   * @param ?int $published_hash
   *   The hash of the published content, if any.
   *
   * @return array
   *   A renderable array representing a table row.
   */
  protected function buildRows(InstanceInterface $builder, ?int $published_hash): array {
    $rows = [];
    // Reindex the array numerically so 0 will always be the 'present' (the
    // default revision).
    $past = \array_values($builder->getPast());

    foreach ($past as $index => $step) {
      /** @var \Drupal\display_builder\InstanceInterface $step */
      $rows[] = $this->buildRow(-\count($past) + $index, $step, $published_hash);
    }

    // Present data.
    $rows[] = $this->buildRow(0, $builder, $published_hash);

    // Reindex the array numerically so 0 will always be the 'present' (the
    // default revision).
    foreach (\array_values($builder->getFuture()) as $index => $step) {
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
      'hash' => $hash,
      'data' => [
        (string) $index,
        $published_hash && ($hash === $published_hash) ? '✅' : '',
        DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $step->getRevisionCreationTime()),
        $step->getRevisionUser()?->getDisplayName(),
        $step->getRevisionLogMessage(),
      ],
      'style' => ($index === 0) ? 'font-weight: bold;' : '',
    ];
  }

  /**
   * Print an alert if the saved step is not in the history.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The instance entity.
   * @param int $published_hash
   *   The hash of the published content.
   *
   * @return array
   *   A renderable array.
   */
  private function printSaveAlert(InstanceInterface $builder, int $published_hash): array {
    $steps = \array_merge($builder->getPast(), [$builder], $builder->getFuture());

    // Every step is a revision of the same instance, so they all share the
    // published hash resolved by the caller. Asking each one for it again
    // would rebuild the buildable plugin once per step.
    foreach ($steps as $step) {
      /** @var \Drupal\display_builder\InstanceInterface $step */
      if ($step->getHash() === $published_hash) {
        return [];
      }
    }

    $params = [
      '%time' => DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $builder->getPublishedTime()),
    ];

    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Published at %time but not visible in logs', $params),
    ];
  }

}
