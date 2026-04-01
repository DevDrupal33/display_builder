<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;

/**
 * History buttons island plugin implementation.
 */
#[Island(
  id: 'history',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('History'),
  description: new TranslatableMarkup('Undo and redo changes.'),
  type: IslandType::Button,
  default_region: 'end',
)]
class HistoryButtons extends IslandPluginToolbarButtonConfigurationBase {

  use IslandReloadEventsTrait;

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    $buttons = [
      $this->isButtonEnabled('undo') ? $this->buildUndoButton($builder, $builder_id) : [],
      $this->isButtonEnabled('redo') ? $this->buildRedoButton($builder, $builder_id) : [],
      $this->isButtonEnabled('clear') ? $this->buildClearButton($builder, $builder_id) : [],
    ];

    if (empty(\array_filter($buttons))) {
      return [];
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => $buttons,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function hasButtons(): array {
    return [
      'undo' => [
        'title' => $this->t('Undo'),
        'description' => $this->t('Undo action, icon is always visible, label is number of undo.'),
        'default' => 'icon_label',
      ],
      'redo' => [
        'title' => $this->t('Redo'),
        'description' => $this->t('Redo action, icon is always visible, label is number of redo.'),
        'default' => 'icon_label',
      ],
      'clear' => [
        'title' => $this->t('Clear'),
        'description' => $this->t('A button to clear the logs history (past and future).'),
        'default' => 'hidden',
      ],
    ];
  }

  /**
   * Builds the undo button.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The builder instance.
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   The undo button render array.
   */
  private function buildUndoButton(InstanceInterface $instance, string $builder_id): array {
    $past = $instance->getPast();
    $undo = $this->buildButton(
      ($this->showLabel('undo') && $past) ? (string) \count($past) : '',
      'undo',
      'arrow-counterclockwise',
      $this->t('Undo (shortcut: u)'),
      ['u' => $this->t('Undo last change')]
    );

    if (empty($past)) {
      $undo['#attributes']['disabled'] = 'disabled';
    }

    return $this->htmxEvents->onUndo($undo, $builder_id);
  }

  /**
   * Builds the redo button.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   The redo button render array.
   */
  private function buildRedoButton(InstanceInterface $builder, string $builder_id): array {
    $future = $builder->getFuture();
    $redo = $this->buildButton(
      ($this->showLabel('redo') && $future) ? (string) \count($future) : '',
      'redo',
      'arrow-clockwise',
      $this->t('Redo (shortcut: r)'),
      ['r' => $this->t('Redo last undone change')]
    );

    if (empty($future)) {
      $redo['#attributes']['disabled'] = 'disabled';
    }

    return $this->htmxEvents->onRedo($redo, $builder_id);
  }

  /**
   * Builds the clear button.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   The clear button render array.
   */
  private function buildClearButton(InstanceInterface $builder, string $builder_id): array {
    $clear = $this->buildButton(
      $this->showLabel('clear') ? $this->t('Clear') : '',
      'clear',
      $this->showIcon('clear') ? 'clock-history' : '',
      $this->t('Clear history (shortcut: Shift+C)'),
      ['C' => $this->t('Clear all changes history (Shift+C)')]
    );
    $clear['#props']['variant'] = 'warning';
    $clear['#attributes']['outline'] = TRUE;

    if (empty($builder->getPast()) && empty($builder->getFuture())) {
      $clear['#attributes']['class'] = ['hidden'];
    }

    return $this->htmxEvents->onClear($clear, $builder_id);
  }

}
