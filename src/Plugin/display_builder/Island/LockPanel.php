<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\IslandWithFormTrait;
use Drupal\display_builder\ThirdPartySettingsInterface;

/**
 * Lock/unlock island.
 */
#[Island(
  id: 'lock',
  enabled_by_default: FALSE,
  label: new TranslatableMarkup('Lock and unlock'),
  description: new TranslatableMarkup('Lock and unlock parts of the display.'),
  type: IslandType::Contextual,
)]
class LockPanel extends IslandPluginBase implements IslandWithFormInterface, ThirdPartySettingsInterface {

  use IslandWithFormTrait;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Lock';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $form += [
      'status' => [
        '#type' => 'select',
        '#title' => $this->t('Status'),
        '#options' => [
          '' => $this->t('Default'),
          'lock' => $this->t('Lock'),
          'unlock' => $this->t('Unlock'),
        ],
        '#default_value' => $this->data['status'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    $status = match ($this->data['status']) {
      'unlock' => '🔓',
      'lock' => '🔐',
      default => '',
    };

    return [$status];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithInstanceData($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadWithInstanceData($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(string $builder_id, array $data): array {
    return $this->reloadWithLocalData($builder_id, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithLocalData($builder_id, []);
  }

  /**
   * {@inheritdoc}
   */
  public function alterNodeRenderable(array $renderable, array $settings, string $node_id, InstanceInterface $instance): array {
    if (empty($settings['status'])) {
      return $renderable;
    }
    $data = $instance->get($node_id);

    return $this->applyStatusToRenderable($settings['status'], $data, $renderable);
  }

  /**
   * Apply lock status to source and some children.
   *
   * @param string $status
   *   The lock status of the current node.
   * @param array $data
   *   The tree node data.
   * @param array $renderable
   *   The renderable to alter, in 'layer' panel, it is a
   *   'display_builder:layer' SDC.
   *
   * @return array
   *   The altered renderable.
   */
  protected function applyStatusToRenderable(string $status, array $data, array $renderable): array {
    if ($status === 'lock') {
      // @todo disable all interactivity, only if the profile doesn't have the lock island.
      // $renderable['#attributes']['inert'] = 'true';
      $renderable['#attributes']['style'] = 'border: solid 2px red';
    }
    elseif ($status === 'unlock') {
      // $renderable['#attributes']['inert'] = 'true';
      $renderable['#attributes']['style'] = 'border: solid 2px green';
    }

    if ($data['source_id'] !== 'component') {
      return $renderable;
    }

    // We may need to also alter the descendant without a clear lock status.
    foreach ($data['source']['component']['slots'] as $slot) {
      $renderable = $this->applyStatusToSlot($status, $slot, $renderable);
    }

    return $renderable;
  }

  /**
   * Apply lock status to slot.
   *
   * @param string $status
   *   The lock status of the current node.
   * @param array $data
   *   The slot data.
   * @param array $renderable
   *   The renderable to alter, in 'layer' panel, it is a
   *   'display_builder:layer' SDC.
   *
   * @return array
   *   The altered renderable.
   */
  protected function applyStatusToSlot(string $status, array $data, array $renderable): array {
    foreach ($data['sources'] as $node) {
      if (!empty($node['third_party_settings']['lock'])) {
        // There is a lock status, so we don't need to alter anything here.
        continue;
      }

      foreach ($renderable['#slots']['children'] as $i => $j) {
        if (($j[1]['#component'] === 'display_builder:dropzone') && isset($renderable['#slots']['children'])) {
          $dropzone = $renderable['#slots']['children'][$i][1];
          $renderable['#slots']['children'][$i][1] = $this->applyStatusToDropzone($status, $data, $dropzone);
        }
      }
    }

    return $renderable;
  }

  /**
   * Apply lock status to dropzone.
   *
   * @param string $status
   *   The lock status of the current node.
   * @param array $data
   *   The slot data.
   * @param array $renderable
   *   The dropzone renderable to alter.
   *
   * @return array
   *   The altered renderable.
   */
  protected function applyStatusToDropzone(string $status, array $data, array $renderable): array {
    foreach ($renderable['#slots']['content'] ?? [] as $delta => $layer) {
      $node = $data['sources'][$delta];
      $renderable['#slots']['content'][$delta] = $this->applyStatusToRenderable($status, $node, $layer);
    }

    return $renderable;
  }

}
