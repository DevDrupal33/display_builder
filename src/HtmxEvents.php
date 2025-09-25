<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Url;

/**
 * The HTMX Events class.
 */
class HtmxEvents {

  use HtmxTrait;

  /**
   * Delete on click.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $node_id
   *   The node id of the source.
   *
   * @return array
   *   The render array.
   */
  public function onClickDelete(array $build, string $builder_id, string $node_id): array {
    $url = new Url(
      'display_builder.api_delete',
      [
        'builder' => $builder_id,
        'node_id' => $node_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'delete');
  }

  /**
   * Save as preset on click.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $node_id
   *   The node id of the source.
   * @param string|\Drupal\Component\Render\MarkupInterface $prompt
   *   The prompt before save.
   *
   * @return array
   *   The render array.
   */
  public function onClickSavePreset(array $build, string $builder_id, string $node_id, MarkupInterface|string $prompt): array {
    $url = new Url(
      'display_builder.api_save_preset',
      [
        'builder' => $builder_id,
        'node_id' => $node_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post', ['hx-prompt' => $prompt]);
  }

  /**
   * Paste on click.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $node_id
   *   The node id to copy.
   * @param string $parent_id
   *   The instance id target.
   * @param string $slot_id
   *   The instance target slot id.
   * @param string $slot_position
   *   The slot position.
   *
   * @return array
   *   The render array.
   */
  public function onClickPaste(array $build, string $builder_id, string $node_id, string $parent_id, string $slot_id, string $slot_position): array {
    $url = new Url(
      'display_builder.api_paste',
      [
        'builder' => $builder_id,
        'node_id' => $node_id,
        'parent_id' => $parent_id,
        'slot_id' => $slot_id,
        'slot_position' => $slot_position,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
  }

  /**
   * Duplicate on placeholder click.
   *
   * @param array $build
   *   The render array.
   * @param string $builder_id
   *   The instance entity ID.
   * @param string $node_id
   *   The node id to copy.
   * @param string $parent_id
   *   The instance id target.
   * @param string $slot_id
   *   The instance target slot id.
   * @param string $slot_position
   *   The slot position.
   *
   * @return array
   *   The render array.
   */
  public function onClickDuplicate(array $build, string $builder_id, string $node_id, string $parent_id, string $slot_id, string $slot_position): array {
    $url = new Url(
      'display_builder.api_duplicate',
      [
        'builder' => $builder_id,
        'node_id' => $node_id,
        'parent_id' => $parent_id,
        'slot_id' => $slot_id,
        'slot_position' => $slot_position,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
  }

  /**
   * Drop a component_id, a block_id, or an instance_id, to the root dropzone.
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
        'builder' => $builder_id,
        'from' => $island_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'dragend consume', 'post');
  }

  /**
   * Drop a component_id, a block_id, or an instance_id, to a component slot.
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
        'builder' => $builder_id,
        'node_id' => $node_id,
        'slot' => $slot,
        'from' => $island_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'dragend consume', 'post');
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
        'builder' => $builder_id,
        'node_id' => $node_id,
      ]
    );

    // Only for icon case, remove suffix without loading label.
    $label = \ucfirst(\trim(\str_replace(['renderable', '_'], ['', ' '], $title)));

    $attributes = [
      'tabindex' => '0',
      'data-node-id' => $node_id,
      // Data used for contextual menu or drawer name.
      'data-node-title' => $label,
      'data-slot-position' => $index,
      'hx-on::after-swap' => \sprintf('Drupal.displayBuilder.handleSecondDrawer(%s, this, event)', $builder_id),
      'hx-on:click' => \sprintf('Drupal.displayBuilder.handleSecondDrawer(%s, this, event)', $builder_id),
    ];

    return $this->setHtmxAttributes($build, $url, 'click consume', 'get', $attributes);
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
    $url = new Url(
      'display_builder.api_update',
      [
        'builder' => $builder_id,
        'node_id' => $node_id,
        'from' => $island_id,
      ]
    );

    $extra_attr = [];

    // Specific Wysiwyg extra code to make it work.
    if (isset($build['source']['value']['#type']) && $build['source']['value']['#type'] === 'text_format') {
      $extra_attr['hx-on:htmx:config-request'] = 'fixWysiwygUpdate(this, event)';
      $build['#attached']['library'][] = 'display_builder/wysiwyg_fixes.js';
    }

    return $this->setHtmxAttributesOnSubKey($build, $url, 'change consume', 'put', $extra_attr, 'source');
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
        'builder' => $builder_id,
        'node_id' => $node_id,
        'from' => $island_id,
      ]
    );

    $extra_attr = [
      'hx-include' => '#' . $build['source']['#id'],
    ];

    // Specific Wysiwyg extra code to make it work.
    if (isset($build['source']['value']['#type']) && $build['source']['value']['#type'] === 'text_format') {
      $extra_attr['hx-on:htmx:config-request'] = 'fixWysiwygUpdate(this, event)';
      $build['#attached']['library'][] = 'display_builder/wysiwyg_fixes.js';
    }

    return $this->setHtmxAttributesOnSubKey($build, $url, 'click consume', 'put', $extra_attr, 'update');
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
        'builder' => $builder_id,
        'node_id' => $node_id,
        'island_id' => $island_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'change', 'put');
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
        'builder' => $builder_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
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
        'builder' => $builder_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
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
        'builder' => $builder_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
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
        'builder' => $builder_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
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
        'builder' => $builder_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
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
  public function onSave(array $build, string $builder_id): array {
    $url = new Url(
      'display_builder.api_save',
      [
        'builder' => $builder_id,
      ]
    );

    return $this->setHtmxAttributes($build, $url, 'click consume', 'post');
  }

  /**
   * Sets HTMX attributes for a given URL, trigger, and method.
   *
   * @param array $build
   *   The render array to modify.
   * @param \Drupal\Core\Url $url
   *   The URL for the HTMX request.
   * @param string $trigger
   *   The HTMX trigger.
   * @param string $method
   *   The HTTP method.
   * @param array $extra_attr
   *   (Optional) Extra attributes to add.
   *
   * @return array
   *   The modified render array.
   */
  private function setHtmxAttributes(array $build, Url $url, string $trigger, string $method, array $extra_attr = []): array {
    $attr = $this->setTrigger($trigger, $method, $url);
    $attr = \array_merge($attr, $extra_attr);
    $build['#attributes'] = \array_merge($build['#attributes'] ?? [], $attr);

    return $build;
  }

  /**
   * Sets HTMX attributes for a given URL, trigger, and method on a subkey.
   *
   * @param array $build
   *   The render array to modify.
   * @param \Drupal\Core\Url $url
   *   The URL for the HTMX request.
   * @param string $trigger
   *   The HTMX trigger.
   * @param string $method
   *   The HTTP method.
   * @param array $extra_attr
   *   (Optional) Extra attributes to add.
   * @param string $source_key
   *   The name of the key to modify, example : update, source.
   *
   * @return array
   *   The modified render array.
   */
  private function setHtmxAttributesOnSubKey(array $build, Url $url, string $trigger, string $method, array $extra_attr, string $source_key): array {
    if (!isset($build[$source_key])) {
      return $build;
    }
    $attr = $this->setTrigger($trigger, $method, $url);
    $attr = \array_merge($attr, $extra_attr);
    $build[$source_key]['#attributes'] = \array_merge($build[$source_key]['#attributes'] ?? [], $attr);

    return $build;
  }

}
