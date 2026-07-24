<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for contextual menu actions (paste, delete, save preset).
 */
interface ApiContextualMenuControllerInterface {

  /**
   * Paste a source.
   *
   * Reads 'node_id' (source to copy), 'parent_id' ('__root__' or a node ID),
   * 'slot_id', and 'slot_position' from the request body - the contextual
   * menu only knows these at click time, so they can't be route parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  public function paste(Request $request, InstanceInterface $display_builder_instance): array;

  /**
   * Delete a source.
   *
   * Reads 'node_id' from the request body - the contextual menu only knows
   * it at click time, so it can't be a route parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  public function delete(Request $request, InstanceInterface $display_builder_instance): array;

  /**
   * Save a source as preset.
   *
   * Reads 'node_id' from the request body - the contextual menu only knows
   * it at click time, so it can't be a route parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  public function saveAsPreset(Request $request, InstanceInterface $display_builder_instance): array;

  /**
   * Paste or merge a source's ui_styles third-party-setting onto another.
   *
   * Reads 'node_id' (paste target), 'source_node_id' (styles to copy from),
   * and 'mode' ('replace', the default, or 'merge') from the request body -
   * the contextual menu only knows these at click time, so they can't be
   * route parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  public function pasteStyles(Request $request, InstanceInterface $display_builder_instance): array;

  /**
   * Clear a source's ui_styles third-party-setting.
   *
   * Reads 'node_id' from the request body - the contextual menu only knows
   * it at click time, so it can't be a route parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  public function deleteStyles(Request $request, InstanceInterface $display_builder_instance): array;

}
