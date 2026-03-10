<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\SourceTree;

/**
 * Index island plugin implementation for Playwright e2e tests.
 */
#[Island(
  id: 'test_index_raw',
  label: new TranslatableMarkup('[Test] Index raw'),
  description: new TranslatableMarkup('Provide internal index information.'),
  type: IslandType::View,
)]
class TestIndexRawPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $tree = new SourceTree($builder->getCurrentState());
    $rawTee = $tree->getNormalizedStructure();

    try {
      $data = Yaml::encode($rawTee);
    }
    catch (\Throwable $th) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['test-result', 'error'],
        ],
        '#value' => $this->t('Failed to print index: @message', ['@message' => $th->getMessage()]),
      ];
    }

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
          '#value' => $data,
          '#attributes' => [
            'style' => 'font-size: 12px; white-space:pre-wrap;',
            'class' => ['test-result-index'],
          ],
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
