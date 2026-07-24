<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Url;

/**
 * The HTMX Events class.
 */
class HtmxEvents {

  public const HTMX_REQUEST = 'click consume';

  /**
   * Delete on click.
   *
   * The node ID isn't known until the contextual menu resolves what was
   * right-clicked, so it's supplied at request time via 'hx-vals'
   * (@see components/contextual_menu/contextual_menu.js) rather than baked into
   * the URL here.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onClickDelete(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_delete',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST);
    $htmx->on('click', \sprintf('Drupal.displayBuilder.handleSecondDrawer(%s, this, event, "close")', $builder_id));
    $htmx->applyTo($build);

    return $build;
  }

  /**
   * Save as preset on click.
   *
   * The node ID isn't known until the contextual menu resolves what was
   * right-clicked, so it's supplied at request time via 'hx-vals'
   * (@see components/contextual_menu/contextual_menu.js) rather than baked into
   * the URL here.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string|\Drupal\Component\Render\MarkupInterface $prompt
   *   The prompt before save.
   *
   * @return array
   *   The render array.
   */
  public function onClickSavePreset(array $build, string $builder_id, MarkupInterface|string $prompt): array {
    $url = new Url(
      'display_builder.api_save_preset',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST);
    $htmx->prompt((string) $prompt);
    $htmx->applyTo($build);

    return $build;
  }

  /**
   * Paste on click.
   *
   * The node/parent/slot IDs aren't known until the contextual menu
   * resolves what was right-clicked and what was previously copied, so
   * they're supplied at request time via 'hx-vals'
   * (@see components/contextual_menu/contextual_menu.js) rather than baked into
   * the URL here.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onClickPaste(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_paste',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * Duplicate on placeholder click.
   *
   * The node/parent/slot IDs aren't known until the contextual menu
   * resolves what was right-clicked, so they're supplied at request time
   * via 'hx-vals' (@see components/contextual_menu/contextual_menu.js) rather
   * than baked into the URL here.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onClickDuplicate(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_duplicate',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * Paste or merge styles on click.
   *
   * Used by both the "Paste styles" and "Merge styles" menu items - they
   * hit the same endpoint and only differ in the 'mode' request value,
   * which the contextual menu sets based on which item was clicked
   * (@see components/contextual_menu/contextual_menu.js), same as the
   * target/source node IDs.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onClickPasteStyles(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_paste_styles',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * Delete styles on click.
   *
   * The node ID isn't known until the contextual menu resolves what was
   * right-clicked, so it's supplied at request time via 'hx-vals'
   * (@see components/contextual_menu/contextual_menu.js) rather than baked into
   * the URL here.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onClickDeleteStyles(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_delete_styles',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * Drop a component_id, a block_id, or an node_id, to the root dropzone.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $island_id
   *   The island initiating the event.
   *
   * @return array
   *   The render array.
   */
  public function onRootDrop(array $build, string $builder_id, string $island_id): array {
    $url = new Url(
      'display_builder.api_root_attach',
      [
        'display_builder_instance' => $builder_id,
        'from' => $island_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('post', $url, 'dragend consume');
    $htmx->on('dragend', \sprintf('Drupal.displayBuilder.handleSecondDrawer(%s, this, event, "dragend")', $builder_id));
    $htmx->applyTo($build);

    return $build;
  }

  /**
   * Drop a component_id, a block_id, or an node_id, to a component slot.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $island_id
   *   The island initiating the event.
   * @param string $node_id
   *   The node id of the source.
   * @param string $slot
   *   The slot.
   *
   * @return array
   *   The render array.
   */
  public function onSlotDrop(array $build, string $builder_id, string $island_id, string $node_id, string $slot): array {
    $url = new Url(
      'display_builder.api_slot_attach',
      [
        'display_builder_instance' => $builder_id,
        'node_id' => $node_id,
        'slot' => $slot,
        'from' => $island_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('post', $url, 'dragend consume');
    $htmx->on('dragend', \sprintf('Drupal.displayBuilder.handleSecondDrawer(%s, this, event, "dragend")', $builder_id));
    $htmx->applyTo($build);

    return $build;
  }

  /**
   * When a component or block is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $node_id
   *   The node id of the source.
   * @param string $title
   *   The instance title.
   * @param int $index
   *   The instance index.
   *
   * @return array
   *   The render array.
   */
  public function onInstanceClick(array $build, string $builder_id, string $node_id, string $title, int $index): array {
    $url = new Url(
      'display_builder.api_get',
      [
        'display_builder_instance' => $builder_id,
        'node_id' => $node_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('get', $url, self::HTMX_REQUEST);
    $htmx->vals(['node_id' => $node_id]);
    $htmx->on('click', \sprintf('Drupal.displayBuilder.handleSecondDrawer(%s, this, event, "click")', $builder_id));
    $htmx->applyTo($build);

    $attributes = [
      'tabindex' => '0',
      'data-node-id' => $node_id,
    ];

    // If not set before we add information for contextual menu or drawer label.
    if (!isset($build['#attributes']['data-node-title'])) {
      // Only for icon case, remove suffix without loading label.
      $attributes['data-node-title'] = \ucfirst(\trim(\str_replace(['renderable', '_'], ['', ' '], $title)));
    }

    if (!isset($build['#attributes']['data-slot-position'])) {
      $attributes['data-slot-position'] = $index;
    }

    $build['#attributes'] = \array_merge($build['#attributes'] ?? [], $attributes);

    return $build;
  }

  /**
   * When a value is changed in an instance island form.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $island_id
   *   The island initiating the event.
   * @param string $node_id
   *   The node id of the source.
   *
   * @return array
   *   The render array.
   */
  public function onInstanceFormChange(array $build, string $builder_id, string $island_id, string $node_id): array {
    if (!isset($build['source'])) {
      return $build;
    }

    $url = new Url(
      'display_builder.api_update',
      [
        'display_builder_instance' => $builder_id,
        'node_id' => $node_id,
        'from' => $island_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('put', $url, 'change consume');

    $htmx->applyTo($build['source']);

    return $build;
  }

  /**
   * When the update button is clicked in an instance island form.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $island_id
   *   The island initiating the event.
   * @param string $node_id
   *   The node id of the source.
   *
   * @return array
   *   The render array.
   */
  public function onInstanceUpdateButtonClick(array $build, string $builder_id, string $island_id, string $node_id): array {
    if (!isset($build['update']) || !isset($build['source']) || !isset($build['source']['#id'])) {
      return $build;
    }
    $url = new Url(
      'display_builder.api_update',
      [
        'display_builder_instance' => $builder_id,
        'node_id' => $node_id,
        'from' => $island_id,
      ]
    );

    $htmx = DisplayBuilderHtmx::request('put', $url, self::HTMX_REQUEST);
    $htmx->include('#' . $build['source']['#id']);

    $htmx->applyTo($build['update']);

    return $build;
  }

  /**
   * When a value is changed in a third party island.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $node_id
   *   The node id of the source.
   * @param string $island_id
   *   The island id.
   *
   * @return array
   *   The render array.
   */
  public function onThirdPartyFormChange(array $build, string $builder_id, string $node_id, string $island_id): array {
    $url = new Url(
      'display_builder.api_third_party_settings_update',
      [
        'display_builder_instance' => $builder_id,
        'node_id' => $node_id,
        'island_id' => $island_id,
      ]
    );

    DisplayBuilderHtmx::request('put', $url, 'change')->applyTo($build);

    return $build;
  }

  /**
   * When the undo button is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onUndo(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_undo',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * When the undo button is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onRedo(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_redo',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * When the restore button is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onReset(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_restore',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * When the revert button is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onRevert(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_revert',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * When the history clear button is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onClear(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_clear',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

  /**
   * When the save button is clicked.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   *
   * @return array
   *   The render array.
   */
  public function onPublish(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_publish',
      [
        'display_builder_instance' => $builder_id,
      ]
    );

    DisplayBuilderHtmx::request('post', $url, self::HTMX_REQUEST)->applyTo($build);

    return $build;
  }

}
