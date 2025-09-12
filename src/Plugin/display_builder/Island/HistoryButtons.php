<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;

/**
 * History buttons island plugin implementation.
 */
#[Island(
  id: 'history',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('History'),
  description: new TranslatableMarkup('Undo and Redo buttons.'),
  type: IslandType::Button,
)]
class HistoryButtons extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['display_clear_button' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
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
    $configuration = $this->getConfiguration();

    if ($configuration['display_clear_button']) {
      return [
        $this->t('With clear button.'),
      ];
    }

    return [
      $this->t('Without clear button.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $builder_id = (string) $builder->id();
    $future = $builder->getCountFuture();
    $past = $builder->getCountPast();

    $undo = $this->buildButton($past ? (string) $past : '', 'undo', 'arrow-counterclockwise', $this->t('Undo (shortcut: u)'), ['u' => $this->t('Undo last change')]);

    if (empty($past)) {
      $undo['#attributes']['disabled'] = 'disabled';
    }
    $redo = $this->buildButton($future ? (string) $future : '', 'redo', 'arrow-clockwise', $this->t('Redo (shortcut: r)'), ['r' => $this->t('Redo last undone change')]);

    if (empty($future)) {
      $redo['#attributes']['disabled'] = 'disabled';
    }

    $clear = $this->buildButton('', 'clear', 'clock-history', $this->t('Clear history (shortcut: C)'), ['C' => $this->t('Clear all changes history')]);
    $clear['#props']['variant'] = 'warning';
    $clear['#attributes']['outline'] = TRUE;

    // Just hide the button to keep it in the dom, if not keyboard shortcut will
    // fail.
    $configuration = $this->getConfiguration();
    if (!$configuration['display_clear_button'] || (empty($past) && empty($future))) {
      $clear['#attributes']['class'] = ['hidden'];
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [
          $this->htmxEvents->onUndo($undo, $builder_id),
          $this->htmxEvents->onRedo($redo, $builder_id),
          $this->htmxEvents->onClear($clear, $builder_id),
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
    // @todo pass \Drupal\display_builder\InstanceInterface object in
    // parameters instead of loading again.
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);

    return $this->addOutOfBand(
      $this->build($builder, []),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
