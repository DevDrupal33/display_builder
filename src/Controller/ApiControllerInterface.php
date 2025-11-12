<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Render\HtmlResponse;
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
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function attachToRoot(Request $request, InstanceInterface $builder): HtmlResponse;

  /**
   * Attach a component_id, a block_id, or an source, to a component slot.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the parent.
   * @param string $slot
   *   Slot.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function attachToSlot(Request $request, InstanceInterface $builder, string $node_id, string $slot): HtmlResponse;

  /**
   * Open source's contextual islands.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   *
   * @return array
   *   The render array response.
   */
  public function get(Request $request, InstanceInterface $builder, string $node_id): array;

  /**
   * Update source.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function update(Request $request, InstanceInterface $builder, string $node_id): HtmlResponse;

  /**
   * Update source's 3rd party settings.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   * @param string $island_id
   *   Island ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function thirdPartySettingsUpdate(Request $request, InstanceInterface $builder, string $node_id, string $island_id): HtmlResponse;

  /**
   * Paste a source.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source.
   * @param string $parent_id
   *   Parent ID.
   * @param string $slot_id
   *   Slot ID.
   * @param string $slot_position
   *   Slot position.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function paste(Request $request, InstanceInterface $builder, string $node_id, string $parent_id, string $slot_id, string $slot_position): HtmlResponse;

  /**
   * Delete a source.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source to delete.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function delete(Request $request, InstanceInterface $builder, string $node_id): HtmlResponse;

  /**
   * Save a source as preset.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param string $node_id
   *   Node ID of the source to save.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function saveAsPreset(Request $request, InstanceInterface $builder, string $node_id): HtmlResponse;

  /**
   * Save display builder instance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function save(Request $request, InstanceInterface $builder): HtmlResponse;

  /**
   * Restore to last save.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function restore(Request $request, InstanceInterface $builder): HtmlResponse;

  /**
   * Revert entity override to default display.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function revert(Request $request, InstanceInterface $builder): HtmlResponse;

  /**
   * Move history to the last past state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function undo(Request $request, InstanceInterface $builder): HtmlResponse;

  /**
   * Move history to the first future state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function redo(Request $request, InstanceInterface $builder): HtmlResponse;

  /**
   * Clear history.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function clear(Request $request, InstanceInterface $builder): HtmlResponse;

}
