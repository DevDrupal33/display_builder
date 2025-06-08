<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;

/**
 * Provide methods missing in UI Patterns.
 */
class SlotSourceProxy {

  public function __construct(
    protected PluginManagerInterface $source_manager,
    private MemoryCacheInterface $memoryCache,
  ) {}

  /**
   * Get the label from data.
   *
   * @param array $data
   *   The data to processed.
   * @param array $contexts
   *   The contexts for this builder_id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label if found.
   */
  public function getLabel(array $data, array $contexts = []): string|TranslatableMarkup {
    $key = 'db_slot_proxy';
    $label_key = crc32(Json::encode($data));
    $slot_labels = [];

    $slot_labels_cache = $this->memoryCache->get($key);
    if ($slot_labels_cache !== FALSE) {
      $slot_labels = $slot_labels_cache->data;
      if (isset($slot_labels[$label_key])) {
        return $slot_labels[$label_key];
      }
    }

    $slot_labels[$label_key] = $this->getLabelFromData($data, $contexts);
    $this->memoryCache->set($key, $slot_labels);

    return $slot_labels[$label_key];
  }

  /**
   * Get the label from data.
   *
   * @param array $data
   *   The data to processed.
   * @param array $contexts
   *   The contexts for this builder_id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label if found.
   */
  private function getLabelFromData(array $data, array $contexts = []): string|TranslatableMarkup {
    $source = $this->source_manager->getSource($data['_instance_id'] ?? '', [], $data, $contexts);
    if (!$source) {
      return '';
    }

    return ($source instanceof SourcesBundlerInterface) ? $source->getOptionLabel($data) : $source->label();
  }

}
