<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_overrides\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Plugin implementation of the source.
 *
 * Slot is explicitly added to prop_types to allow getPropValue
 * to return a renderable array in case of slot prop type.
 */
#[Source(
  id: 'main_page_content',
  label: new TranslatableMarkup('[Page] Main content'),
  description: new TranslatableMarkup('The Drupal `Main page content` block (system_main_block).'),
  prop_types: ['slot'],
  tags: [],
  context_requirements: ['is_display_builder_page_layout'],
  context_definitions: []
)]
class MainPageContentSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $isSlot = ($this->propDefinition['ui_patterns']['type_definition']->getPluginId() === 'slot');

    return $isSlot ? [] : '';
  }

  /**
   * Get the value from settings.
   *
   * @param \Drupal\ui_patterns\PropTypeInterface|null $prop_type
   *   The prop type.
   */
  public function getValue(?PropTypeInterface $prop_type = NULL): mixed {
    return $this->configuration['settings'];
  }

}
