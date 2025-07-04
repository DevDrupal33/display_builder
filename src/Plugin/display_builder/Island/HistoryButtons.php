<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * History buttons island plugin implementation.
 */
#[Island(
  id: 'history',
  label: new TranslatableMarkup('History'),
  description: new TranslatableMarkup('Undo and Redo buttons.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'u' => new TranslatableMarkup('Undo last change'),
    'r' => new TranslatableMarkup('Redo last change'),
    'C' => new TranslatableMarkup('(shift c) Clear history'),
  ],
)]
class HistoryButtons extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $future = $this->stateManager->getCountFuture($builder_id);
    $past = $this->stateManager->getCountPast($builder_id);

    $undo = $this->buildButton($past ? (string) $past : '', $this->t('Undo'), 'u', empty($past), 'arrow-counterclockwise');
    $redo = $this->buildButton($future ? (string) $future : '', $this->t('Redo'), 'r', empty($future), 'arrow-clockwise');
    $clear = [];

    if (!empty($past) || !empty($future)) {
      $clear = $this->buildButton($this->t('Clear'), '', 'C', (empty($past) && empty($future)));
      $clear['#props']['variant'] = 'warning';
      $clear['#attributes']['outline'] = TRUE;
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
    return $this->addOutOfBand(
      $this->build($builder_id, []),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
