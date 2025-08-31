<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Info island plugin implementation.
 */
#[Island(
  id: 'info',
  label: new TranslatableMarkup('Info'),
  description: new TranslatableMarkup('Information about the active element.'),
  type: IslandType::Contextual,
)]
class InfoPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $build = parent::build($builder, $data, $options);

    if (!$this->isApplicable()) {
      return $build;
    }

    $component_id = $this->data['source']['component']['component_id'];
    $component = $this->sdcManager->find($component_id);
    $build[] = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
      '#value' => $component->metadata->name,
    ];
    $thumbnail = $component->metadata->getThumbnailPath();

    if ($thumbnail) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => '/' . $thumbnail,
          'style' => 'width: 100%',
        ],
      ];
    }
    $definition = $this->sdcManager->getDefinition($component_id);
    $build[] = [
      '#theme' => 'ui_patterns_component_metadata',
      '#component' => $definition,
    ];

    return $build;
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
    return $this->reloadWithLocalData($builder_id, $data, NULL);
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
  public function isApplicable(): bool {
    // No need for isset($data['_instance_id']) here.
    return isset($this->data['source_id']) && ($this->data['source_id'] === 'component');
  }

}
