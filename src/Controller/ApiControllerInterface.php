<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for the REST API.
 */
interface ApiControllerInterface {

  /**
   * Attach a component_id, a block_id, or an instance_id, to the root.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function attachToRoot(Request $request, string $builder_id): HtmlResponse;

  /**
   * Attach a component_id, a block_id, or an instance_id, to a component slot.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID.
   * @param string $slot
   *   Slot.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function attachToSlot(Request $request, string $builder_id, string $instance_id, string $slot): HtmlResponse;

  /**
   * Open instance_id's contextual islands.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID.
   *
   * @return array
   *   The render array response.
   */
  public function getInstance(Request $request, string $builder_id, string $instance_id): array;

  /**
   * Save builder.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function save(Request $request, string $builder_id): HtmlResponse;

  /**
   * Update instance_id.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID.
   *
   * @return array
   *   The render array response.
   */
  public function updateInstance(Request $request, string $builder_id, string $instance_id): array;

  /**
   * Update instance_id.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID.
   * @param string $island_id
   *   Island ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function thirdPartySettingsUpdate(Request $request, string $builder_id, string $instance_id, string $island_id): HtmlResponse;

  /**
   * Delete an instance in a builder.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID to delete.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function deleteInstance(Request $request, string $builder_id, string $instance_id): HtmlResponse;

  /**
   * Save an instance as preset from a builder.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID to save.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function saveInstanceAsPreset(Request $request, string $builder_id, string $instance_id): HtmlResponse;

  /**
   * Move history to the last past state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function undo(Request $request, string $builder_id): HtmlResponse;

  /**
   * Move history to the first future state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function redo(Request $request, string $builder_id): HtmlResponse;

  /**
   * Restore to last save.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param string $builder_id
   *   Builder ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function restore(Request $request, string $builder_id): HtmlResponse;

}
