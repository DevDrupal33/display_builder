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
    $buttons = [
      $this->isButtonEnabled('undo') ? $this->buildUndoButton($builder) : [],
      $this->isButtonEnabled('redo') ? $this->buildRedoButton($builder) : [],
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
        'description' => $this->t('Undo action.'),
        'default' => 'icon',
      ],
      'redo' => [
        'title' => $this->t('Redo'),
        'description' => $this->t('Redo action.'),
        'default' => 'icon',
      ],
    ];
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
      $this->showLabel('undo') ? $this->t('Undo') : '',
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
      $this->showLabel('redo') ? $this->t('Redo') : '',
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

}
