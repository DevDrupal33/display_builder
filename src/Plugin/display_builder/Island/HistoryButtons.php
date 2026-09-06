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
 * History buttons island plugin implementation.
 *
 * Undo/redo plus a third button opening the log of past and future steps -
 * former job of the separate `logs` View island, merged here because it had
 * no action of its own and sat in the main tab strip next to Canvas and
 * Scaffold for no reason: it is the same history this island already steps
 * through. @see https://www.drupal.org/project/display_builder/issues/3620417
 */
#[Island(
  id: 'history',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('History'),
  description: new TranslatableMarkup('Undo, redo and browse changes history.'),
  type: IslandType::Button,
  region: 'end',
)]
class HistoryButtons extends IslandPluginBase {

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
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [
          $this->buildUndoButton($builder),
          $this->buildRedoButton($builder),
          $this->buildLogsDropdown($builder),
        ],
      ],
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
   * Builds the undo button.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   *
   * @return array
   *   The undo button render array.
   */
  private function buildUndoButton(InstanceInterface $builder): array {
    $past = $builder->getPast();
    $undo = $this->buildButton(
      '',
      'undo',
      'arrow-counterclockwise',
      $this->t('Undo (shortcut: Ctrl/Cmd+Z)'),
      ['mod+z u' => $this->t('Undo last change')]
    );

    if (empty($past)) {
      $undo['#attributes']['disabled'] = 'disabled';
    }

    return $this->htmxEvents->onUndo($undo, (string) $builder->id());
  }

  /**
   * Builds the redo button.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   *
   * @return array
   *   The redo button render array.
   */
  private function buildRedoButton(InstanceInterface $builder): array {
    $future = $builder->getFuture();
    $redo = $this->buildButton(
      '',
      'redo',
      'arrow-clockwise',
      $this->t('Redo (shortcut: Ctrl/Cmd+Shift+Z)'),
      ['mod+shift+z r' => $this->t('Redo last undone change')]
    );

    if (empty($future)) {
      $redo['#attributes']['disabled'] = 'disabled';
    }

    return $this->htmxEvents->onRedo($redo, (string) $builder->id());
  }

  /**
   * Builds the dropdown holding the logs table.
   *
   * Same shape as `StateButtons::buildStateDropdown()`: a caret-only trigger
   * button, an `sl-dropdown` with the table as its content slot.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   *
   * @return array
   *   The dropdown render array.
   */
  private function buildLogsDropdown(InstanceInterface $builder): array {
    // No $tooltip param: a truthy `tooltip` makes button.twig wrap the
    // <sl-button> in an <sl-tooltip>, and dropdown.twig's slot="trigger"
    // lands on the inner <sl-button> - not a direct child of <sl-dropdown>,
    // so the slot assignment silently fails and the trigger renders at zero
    // size. `title` gives the icon its accessible name the same way
    // (button.twig falls back to attributes['title']) without the wrapper.
    // Same pattern as StateButtons::buildStateDropdown().
    $trigger = $this->buildButton(
      '',
      'logs',
      'clock-history',
      NULL,
      ['o' => $this->t('Show the logs')],
    );
    $trigger['#attributes']['title'] = $this->t('Show the logs');

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#props' => [
        'placement' => 'bottom-end',
      ],
      '#slots' => [
        'button' => $trigger,
        'content' => $this->buildLogsPanel($builder),
      ],
      // Pinned open across the island rebuilds that undo, redo and every
      // other builder event trigger, so the log can be watched while
      // stepping through it. @see assets/js/history.js.
      '#attributes' => [
        'data-logs-dropdown' => TRUE,
      ],
      '#attached' => [
        'library' => ['display_builder/history'],
      ],
    ];
  }

  /**
   * Builds the dropdown panel: a header with a close button, then the table.
   *
   * A surface of its own rather than a bare table: sl-dropdown's panel is
   * transparent and the table sits in the light DOM, so on its own it would
   * take the theme's table styling, or the lack of it.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   *
   * @return array
   *   The panel render array.
   *
   * @see components/toolbar/toolbar.css
   */
  private function buildLogsPanel(InstanceInterface $builder): array {
    $published_hash = $builder->getPublishedHash();

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['db-background', 'db-logs-dropdown'],
      ],
      'header' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['db-logs-dropdown__header'],
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('History'),
        ],
        'close' => [
          '#type' => 'component',
          '#component' => 'display_builder:icon_button',
          '#props' => [
            'icon' => 'x-lg',
            'label' => $this->t('Close'),
          ],
          '#attributes' => [
            'data-logs-close' => TRUE,
            'data-testid' => 'logs_close',
          ],
        ],
      ],
      'table' => [
        '#theme' => 'table',
        '#header' => [
          ['data' => $this->t('Step')],
          ['data' => $this->t('Published')],
          ['data' => $this->t('Time')],
          ['data' => $this->t('User')],
          ['data' => $this->t('Message')],
        ],
        '#rows' => $this->buildRows($builder, $published_hash),
      ],
      'alert' => $published_hash ? $this->printSaveAlert($builder, $published_hash) : [],
    ];
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
