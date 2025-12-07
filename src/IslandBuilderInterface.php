<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for island plugins with a builder related functionality.
 */
interface IslandBuilderInterface {

  /**
   * Build renderable from state data.
   *
   * @param string $instance_id
   *   Display Builder instance ID.
   * @param string $node_id
   *   Tree node ID.
   * @param array $data
   *   The UI Patterns 2 form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  public function buildSingleComponent(string $instance_id, string $node_id, array $data, int $index = 0): ?array;

  /**
   * Build renderable from state data.
   *
   * @param string $instance_id
   *   Display Builder instance ID.
   * @param string $node_id
   *   Tree node ID.
   * @param array $data
   *   The UI Patterns 2 form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  public function buildSingleBlock(string $instance_id, string $node_id, array $data, int $index = 0): ?array;

}
