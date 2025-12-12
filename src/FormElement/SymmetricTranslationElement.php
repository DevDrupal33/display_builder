<?php

declare(strict_types=1);

namespace Drupal\display_builder\FormElement;

use Drupal\config_translation\FormElement\ListElement;
use Drupal\Core\Language\LanguageInterface;

/**
 * Defines the list element for the configuration translation interface.
 */
class SymmetricTranslationElement extends ListElement {

  /**
   * {@inheritdoc}
   */
  public function getTranslationBuild(LanguageInterface $source_language, LanguageInterface $translation_language, $source_config, $translation_config, array $parents, $base_key = NULL) {
    return [
      'sources' => [
        '#type' => 'component_slot_form',
        '#default_value' => [
          'sources' => $translation_config,
        ],
      ],
    ];
  }

}
