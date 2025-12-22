<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for island plugins providing third party settings.
 */
interface ThirdPartySettingsInterface {

  /**
   * Alter node renderable.
   *
   * This is not the same as RenderableAltererInterface::alterElement() which is
   * altering the resulting display, not the building tool.
   *
   * @param array $renderable
   *   The renderable array of the tree node.
   * @param array $settings
   *   The third party settings.
   * @param string $node_id
   *   The tree node ID.
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The instance entity.
   *
   * @return array
   *   The altered renderable array of the tree node.
   */
  public function alterNodeRenderable(array $renderable, array $settings, string $node_id, InstanceInterface $instance): array;

  /**
   * Get settings summary renderable.
   *
   * @return array|null
   *   The renderable summary including translations.
   */
  public function getSummary(): ?array;

}
