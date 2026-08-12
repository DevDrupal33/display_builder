<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for the REST API.
 */
interface ApiControllerInterface {

  /**
   * Attach a component_id, a block_id, or an existing source, to the root.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   */
  public function attachToRoot(Request $request, InstanceInterface $display_builder_instance): array;

  /**
   * Attach a component_id, a block_id, or an source, to a component slot.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the parent.
   * @param string $slot
   *   Slot.
   *
   * @return array
   *   A renderable array
   */
  public function attachToSlot(Request $request, InstanceInterface $display_builder_instance, string $node_id, string $slot): array;

  /**
   * Open source's contextual islands.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   *
   * @return array
   *   The render array response.
   */
  public function get(Request $request, InstanceInterface $display_builder_instance, string $node_id): array;

  /**
   * Rebuild a single island which was deferred while it was off screen.
   *
   * Panels hidden behind an inactive tab or a closed sidebar drawer are
   * skipped by the event fan-out, so they go stale. The client calls this when
   * the user brings one back into view.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $island_id
   *   The island plugin ID to rebuild.
   *
   * @return array
   *   A renderable array carrying the island's out-of-band swap.
   *
   * @see \Drupal\display_builder\Event\DisplayBuilderEventsSubscriber::shouldDefer()
   */
  public function reloadIsland(Request $request, InstanceInterface $display_builder_instance, string $island_id): array;

  /**
   * Update source.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   *
   * @return array
   *   A renderable array
   */
  public function update(Request $request, InstanceInterface $display_builder_instance, string $node_id): array;

  /**
   * Update source's 3rd party settings.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   * @param string $island_id
   *   Island ID.
   *
   * @return array
   *   A renderable array
   */
  public function thirdPartySettingsUpdate(Request $request, InstanceInterface $display_builder_instance, string $node_id, string $island_id): array;

  /**
   * Move history to the last past state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   */
  public function undo(Request $request, InstanceInterface $display_builder_instance): array;

  /**
   * Move history to the first future state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   */
  public function redo(Request $request, InstanceInterface $display_builder_instance): array;

}
