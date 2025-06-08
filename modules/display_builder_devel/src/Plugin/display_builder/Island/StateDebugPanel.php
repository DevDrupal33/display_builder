<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Plugin\display_builder\Island;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * State debug island plugin implementation.
 */
#[Island(
  id: 'state_debug',
  label: new TranslatableMarkup('State'),
  type: IslandType::View,
)]
class StateDebugPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    return [
      [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#attributes' => [
          'class' => ['language-yaml'],
        ],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'code',
          '#value' => Yaml::encode($data),
          '#attributes' => [
            'style' => 'font-size: 11px; white-space:pre-wrap;',
          ],
        ],
      ],
      [
        '#attached' => [
          'library' => ['display_builder_devel/prism.js'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, ?string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

}
