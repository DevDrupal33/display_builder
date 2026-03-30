<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Provides default event handler implementations that reload the island.
 *
 * Use this trait in island plugins that must reload their full content in
 * response to any structural change in the builder tree. It implements the
 * six most common event handler methods from IslandEventSubscriberInterface
 * by delegating to reloadWithGlobalData().
 *
 * Plugins that also need to react to onSave() or onPresetSave() must
 * implement those methods explicitly.
 *
 * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */
trait IslandReloadEventsTrait {

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
  public function onHistoryChange(InstanceInterface $instance): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function onRestore(InstanceInterface $instance): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function onRevert(InstanceInterface $instance): array {
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
