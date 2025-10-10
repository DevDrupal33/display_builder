<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\IslandType;

/**
 * History buttons island plugin implementation.
 */
#[Island(
  id: 'history',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('History'),
  description: new TranslatableMarkup('Undo and redo changes.'),
  type: IslandType::Button,
)]
class HistoryButtons extends IslandPluginToolbarButtonConfigurationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration();
    $configuration['display_clear_button'] = TRUE;

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $configuration = $this->getConfiguration();

    $form['display_clear_button'] = [
      '#title' => $this->t('Enable the "Clear" button'),
      '#description' => $this->t('A button to clear the logs history (past and future).'),
      '#type' => 'checkbox',
      '#default_value' => $configuration['display_clear_button'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $summary = [];

    $configuration = $this->getConfiguration();

    $summary[] = $configuration['display_clear_button'] ? $this->t('With clear button.') : $this->t('Without clear button.');

    return \array_merge($summary, parent::configurationSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function hasButtons(): array {
    return [
      'undo' => ['label' => FALSE, 'icon' => TRUE],
      'redo' => ['label' => FALSE, 'icon' => TRUE],
      'clear' => ['label' => FALSE, 'icon' => TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [
          $this->buildUndoButton($builder, $builder_id),
          $this->buildRedoButton($builder, $builder_id),
          $this->buildClearButton($builder, $builder_id),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * Rebuilds the island with the given builder ID.
   *
   * @param string $builder_id
   *   The ID of the builder.
   *
   * @return array
   *   The rebuilt island.
   */
  private function rebuild(string $builder_id): array {
    if (!$this->builder) {
      // @todo pass \Drupal\display_builder\InstanceInterface object in
      // parameters instead of loading again.
      /** @var \Drupal\display_builder\InstanceInterface $builder */
      $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
      $this->builder = $builder;
    }

    return $this->addOutOfBand(
      $this->build($this->builder),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

  /**
   * Builds the undo button.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   The undo button render array.
   */
  private function buildUndoButton(InstanceInterface $builder, string $builder_id): array {
    $past = $builder->getCountPast();
    $undo = $this->buildButton(
      ($this->showLabel('undo') && $past) ? (string) $past : '',
      'undo',
      $this->showIcon('undo') ? 'arrow-counterclockwise' : '',
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
    $future = $builder->getCountFuture();
    $redo = $this->buildButton(
      ($this->showLabel('redo') && $future) ? (string) $future : '',
      'redo',
      $this->showIcon('redo') ? 'arrow-clockwise' : '',
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
    $past = $builder->getCountPast();
    $future = $builder->getCountFuture();
    $clear = $this->buildButton(
      $this->showLabel('clear') ? $this->t('Clear') : '',
      'clear',
      $this->showIcon('clear') ? 'clock-history' : '',
      $this->t('Clear history (shortcut: C)'),
      ['C' => $this->t('Clear all changes history')]
    );
    $clear['#props']['variant'] = 'warning';
    $clear['#attributes']['outline'] = TRUE;

    $configuration = $this->getConfiguration();

    if (!$configuration['display_clear_button'] || (empty($past) && empty($future))) {
      $clear['#attributes']['class'] = ['hidden'];
    }

    return $this->htmxEvents->onClear($clear, $builder_id);
  }

}
