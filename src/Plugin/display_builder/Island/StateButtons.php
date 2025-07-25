<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * State buttons island plugin implementation.
 */
#[Island(
  id: 'state',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('State'),
  description: new TranslatableMarkup('Buttons to publish and reset the display.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'S' => new TranslatableMarkup('(shift+s) Save this display builder'),
  ],
)]
class StateButtons extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    if (!$this->stateManager->canSaveContextsRequirement($builder_id)) {
      return [];
    }

    $hasSave = $this->stateManager->hasSave($builder_id);
    $saveIsCurrent = $hasSave ? $this->stateManager->saveIsCurrent($builder_id) : FALSE;

    if ($saveIsCurrent) {
      // @todo disabled is lost on edit when saved, hide for now.
      // phpcs:disable
      // $save = $this->buildButton($this->t('Saved'), $this->t('Display saved at current state.'), '', TRUE, 'save2-fill');
      // phpcs:enable
      $save = [];
    }
    else {
      $save = $this->buildButton($this->t('Save'), $this->t('Save this display'), 'S', FALSE);
      $save['#props']['variant'] = 'primary';
      $save['#attributes']['outline'] = TRUE;
    }

    if (!$saveIsCurrent) {
      $restore = $this->buildButton($this->t('Restore'), $this->t('Restore to last saved version'), 'R');
      $restore['#props']['variant'] = 'warning';
      $restore['#attributes']['outline'] = TRUE;
    }
    else {
      $restore = [];
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [
          $this->htmxEvents->onSave($save, $builder_id),
          $this->htmxEvents->onReset($restore, $builder_id),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onSave(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
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
  public function onUpdate(string $builder_id, string $instance_id, ?string $current_island_id): array {
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
    return $this->addOutOfBand(
      $this->build($builder_id, []),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
