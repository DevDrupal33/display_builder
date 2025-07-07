<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Index debug island plugin implementation.
 */
#[Island(
  id: 'index_debug',
  label: new TranslatableMarkup('Index'),
  type: IslandType::View,
)]
class IndexDebugPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $build = [
      '#theme' => 'table',
      '#header' => [
        ['data' => 'Instance ID'],
        ['data' => '(Simplified) Path'],
        ['data' => 'Source'],
      ],
      '#rows' => [],
    ];
    $index = $this->stateManager->getPathIndex($builder_id);
    $index = \array_map([$this, 'printSimplifyPath'], $index);
    asort($index);
    $duplicates = \array_diff_assoc($index, \array_unique($index));

    foreach ($index as $instance_id => $path) {
      $data = $this->stateManager->get($builder_id, (string) $instance_id);
      $build['#rows'][] = [
        'data' => [
          $instance_id,
          \in_array($path, $duplicates, TRUE) ? $path . ' ❌' : $path,
          $data['source_id'] ?? '❌',
        ],
      ];
    }

    return $build;
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
  public function onUpdate(string $builder_id, ?string $instance_id, ?string $current_island_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * Simplifies a given path by removing unnecessary parts.
   *
   * This method takes an array representing a path and simplifies it by
   * removing specific parts of the path. The simplified path is then returned
   * as a string.
   *
   * @param array $path
   *   The path to be simplified.
   *
   * @return string
   *   The simplified path.
   */
  private function printSimplifyPath(array $path): string {
    $path = '/' . implode('/', $path);
    $path = str_replace('/source/component/slots/', '/', $path);

    return str_replace('/sources/', '/', $path);
  }

}
