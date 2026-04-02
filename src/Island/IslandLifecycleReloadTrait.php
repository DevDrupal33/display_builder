<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\InstanceInterface;

/**
 * Reload implementation for IslandLifecycleEventsInterface methods.
 *
 * Provides onHistoryChange, onRestore, and onRevert handlers that all
 * delegate to reloadWithGlobalData(), which the using island plugin must
 * provide.
 *
 * Use this trait when an island should fully reload its content in response
 * to history pointer changes, state restores, or entity view reverts.
 */
trait IslandLifecycleReloadTrait {

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

}
