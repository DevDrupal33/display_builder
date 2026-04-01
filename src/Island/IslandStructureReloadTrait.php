<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\InstanceInterface;

/**
 * Reload implementation for IslandStructureEventsInterface methods.
 *
 * Provides onAttachToRoot, onAttachToSlot, onMove, onUpdate, and onDelete
 * handlers that all delegate to reloadWithGlobalData(), which the using
 * island plugin must provide.
 *
 * Use this trait when an island should fully reload its content in response
 * to any structural mutation of the builder tree.
 *
 * @see \Drupal\display_builder\Island\IslandStructureEventsInterface
 * @see \Drupal\display_builder\Island\IslandReloadEventsTrait
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
trait IslandStructureReloadTrait {

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array {
    return $this->reloadWithGlobalData($instance);
  }

}
