<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase;
use Drupal\ui_patterns\Attribute\Source;

/**
 * Plugin implementation of the source.
 *
 * The prop type is a string on a real page, where the route's title arrives as
 * the settings. In a builder the node sits in a slot instead, and gets the
 * region placeholder.
 *
 * Slot is declared alongside string so a title dropped in a slot is taken
 * natively rather than converted: the string-to-slot conversion escapes what
 * it is given, and both what this returns there - the region placeholder and
 * the page's own title markup - are render arrays.
 *
 * @see \Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant::replaceTitleAndContent()
 */
#[Source(
  id: 'page_title',
  label: new TranslatableMarkup('[Page] Title'),
  description: new TranslatableMarkup('The Drupal `Page title` block (page_title).'),
  prop_types: ['string', 'slot'],
  tags: [],
  context_requirements: ['page'],
  context_definitions: [
    'page' => new ContextDefinition('uri', label: new TranslatableMarkup('Page'), required: TRUE),
  ]
)]
class PageTitleSource extends PageRegionSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function regionLabel(): TranslatableMarkup {
    return new TranslatableMarkup('Page title');
  }

}
