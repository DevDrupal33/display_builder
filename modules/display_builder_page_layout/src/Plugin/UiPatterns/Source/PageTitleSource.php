<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\UiPatterns\Source;

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
  id: 'page_title',
  label: new TranslatableMarkup('[Page] Title'),
  description: new TranslatableMarkup('The Drupal `Page title` block (page_title).'),
  prop_types: ['string'],
  tags: [],
  context_requirements: ['page'],
  context_definitions: []
)]
class PageTitleSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    return '';
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
