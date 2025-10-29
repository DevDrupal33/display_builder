<?php

declare(strict_types=1);

namespace Drupal\display_builder\Config;

use Drupal\Core\Config\ConfigFactory;

/**
 * XXX.
 */
class SymmetricTranslationConfigFactory extends ConfigFactory {

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $names, $immutable = TRUE) {
    $original = $this->storage->readMultiple($names);
    $translations = $this->loadOverrides($names);

    foreach ($translations as $name => $config) {
      foreach ($config as $property => $value) {
        if (!\is_array($value)) {
          continue;
        }

        // @todo check config schema data type instead of property name.
        if ($property === 'sources') {
          $tree = $this->translateSources($original[$name][$property], $value);
          // $this->configFactoryOverrides[$name][$property] = $tree;
        }
      }
    }

    return parent::doLoadMultiple($names, $immutable);
  }

  /**
   * Translate sources.
   *
   * @todo share logic with UI Patterns.
   * https://www.drupal.org/project/ui_patterns/issues/3548884
   */
  protected function translateSources(array $sources, array $translations): array {
    if (empty($translations)) {
      return $sources;
    }

    foreach ($sources as $delta => $source) {
      $sources[$delta] = $this->translateSource($source, $translations);
    }

    return $sources;
  }

  /**
   * Translate source.
   */
  protected function translateSource(array $source, array $translations): array {
    foreach ($translations as $index => $translation) {
      if (!isset($translation['source'])) {
        continue;
      }

      if ($translation['node_id'] === $source['node_id'] && $translation['source_id'] === $source['source_id']) {
        $source['source'] = $translation['source'];
        unset($translations[$index]);

        break;
      }
    }

    // Let's continue the exploration.
    if ($source['source_id'] !== 'component') {
      return $source;
    }

    if (!isset($source['source']['component']['slots'])) {
      return $source;
    }

    foreach ($source['source']['component']['slots'] as $slot_id => $slot) {
      if (!isset($slot['sources'])) {
        continue;
      }
      $slot['sources'] = $this->translateSources($slot['sources'], $translations);
      $source['source']['component']['slots'][$slot_id] = $slot;
    }

    return $source;
  }

}
