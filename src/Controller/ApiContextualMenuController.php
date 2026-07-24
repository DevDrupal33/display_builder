<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for the Display Builder contextual menu routes.
 */
class ApiContextualMenuController extends ApiControllerBase implements ApiContextualMenuControllerInterface {

  /**
   * {@inheritdoc}
   */
  public function paste(Request $request, InstanceInterface $display_builder_instance): array {
    $node_id = (string) $request->request->get('node_id', '');
    $parent_id = (string) $request->request->get('parent_id', '__root__');
    $slot_id = (string) $request->request->get('slot_id', '');
    $slot_position = (string) $request->request->get('slot_position', '0');

    $this->builder = $display_builder_instance;
    $dataToCopy = $display_builder_instance->getNode($node_id);

    // Keep flag for move or attach to root.
    $is_paste_root = FALSE;
    $new_node_id = NULL;

    if (isset($dataToCopy['source_id'], $dataToCopy['source'])) {
      $source_id = $dataToCopy['source_id'];
      // Use reference to ensure modifications by recursiveRefreshNodeId
      // persist.
      $data = &$dataToCopy['source'];

      // Refresh nested node_ids.
      self::recursiveRefreshNodeId($data);

      // If no parent we are on root.
      // @todo for duplicate and not parent root seems not detected and copy is inside the slot.
      if ($parent_id === '__root__') {
        $is_paste_root = TRUE;
        $new_node_id = $display_builder_instance->attachToRoot(0, $source_id, $data, $dataToCopy['third_party_settings'] ?? []);
      }
      else {
        $new_node_id = $display_builder_instance->attachToSlot($parent_id, $slot_id, (int) $slot_position, $source_id, $data, $dataToCopy['third_party_settings'] ?? []);
      }
    }
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(
      $is_paste_root ? DisplayBuilderEvents::ON_ATTACH_TO_ROOT : DisplayBuilderEvents::ON_ATTACH_TO_SLOT,
      NULL,
      $new_node_id,
      $is_paste_root ? NULL : $parent_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function delete(Request $request, InstanceInterface $display_builder_instance): array {
    $node_id = (string) $request->request->get('node_id', '');
    $parent_id = $display_builder_instance->getParentId($node_id);
    $display_builder_instance->remove($node_id);
    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(
      DisplayBuilderEvents::ON_DELETE,
      NULL,
      $node_id,
      $parent_id
    );
  }

  /**
   * {@inheritdoc}
   */
  public function saveAsPreset(Request $request, InstanceInterface $display_builder_instance): array {
    $node_id = (string) $request->request->get('node_id', '');
    $label = (string) $this->t('New preset');
    $data = $display_builder_instance->getNode($node_id);
    self::cleanPreset($data);

    $preset_storage = $this->entityTypeManager()->getStorage('pattern_preset');
    $label = $request->headers->get('hx-prompt', $label) ?: $label;
    // In HTTP headers, only ASCII is guaranteed to work but historically,
    // HTTP has allowed header values with the ISO-8859-1 charset.
    $label = \mb_convert_encoding($label, 'UTF-8', 'ISO-8859-1');

    // Build a valid config-entity machine name from the label. Config IDs
    // must match [a-z0-9_] and must not start with a digit.
    $base_id = 'preset_' . \preg_replace('/[^a-z0-9_]+/', '_', \mb_strtolower($label));
    $base_id = \trim($base_id, '_');
    $id = $base_id;
    $suffix = 1;

    while ($preset_storage->load($id) !== NULL) {
      $id = $base_id . '_' . $suffix++;
    }

    $preset = $preset_storage->create([
      'id' => $id,
      'label' => $label,
      'status' => TRUE,
      'description' => '',
      'sources' => $data,
    ]);
    $preset->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_PRESET_SAVE);
  }

  /**
   * {@inheritdoc}
   */
  public function pasteStyles(Request $request, InstanceInterface $display_builder_instance): array {
    $node_id = (string) $request->request->get('node_id', '');
    $source_node_id = (string) $request->request->get('source_node_id', '');
    $mode = (string) $request->request->get('mode', 'replace');

    $default_styles = ['selected' => [], 'extra' => ''];
    $source = $display_builder_instance->getNode($source_node_id);
    $styles = $source['third_party_settings']['styles'] ?? $default_styles;

    if ($mode === 'merge') {
      $target = $display_builder_instance->getNode($node_id);
      $current = $target['third_party_settings']['styles'] ?? $default_styles;
      $styles = [
        // Later array wins on overlapping keys: the copied styles override
        // the target's own selection for any style category they share,
        // while categories only the target had are preserved.
        'selected' => \array_merge($current['selected'] ?? [], $styles['selected'] ?? []),
        'extra' => \trim(\trim((string) ($current['extra'] ?? '')) . ' ' . \trim((string) ($styles['extra'] ?? ''))),
      ];
    }

    $display_builder_instance->setThirdPartySettings($node_id, 'styles', $styles);
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_UPDATE, NULL, $node_id);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteStyles(Request $request, InstanceInterface $display_builder_instance): array {
    $node_id = (string) $request->request->get('node_id', '');
    $display_builder_instance->setThirdPartySettings($node_id, 'styles', ['selected' => [], 'extra' => '']);
    $display_builder_instance->save();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_UPDATE, NULL, $node_id);
  }

  /**
   * Recursively regenerate the node_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function recursiveRefreshNodeId(array &$array): void {
    if (isset($array['node_id'])) {
      $array['node_id'] = \bin2hex(\random_bytes(8));
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::recursiveRefreshNodeId($value);
      }
    }
  }

  /**
   * Recursively clean the node data for export or preset saving.
   *
   * Unset node_id and remove empty values.
   *
   * @param array $array
   *   The array reference.
   */
  private static function cleanPreset(array &$array): void {
    unset($array['node_id']);

    foreach ($array as $key => &$value) {
      if (\is_array($value)) {
        self::cleanPreset($value);

        // Remove empty values to reduce size and noise in the exported preset.
        if (isset($value['source_id'], $value['source']['value']) && $value['source']['value'] === '') {
          unset($array[$key]);
        }
      }

      if ($key === 'extra' && empty($value)) {
        unset($array[$key]);
      }

      if ($key === 'third_party_settings' && empty($value)) {
        unset($array[$key]);
      }

      if ($key === 'variant_id' && $value === NULL) {
        unset($array[$key]);
      }
    }
  }

}
