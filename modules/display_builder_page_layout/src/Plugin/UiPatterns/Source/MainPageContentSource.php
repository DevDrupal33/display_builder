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
 * Slot is explicitly added to prop_types to allow getPropValue
 * to return a renderable array in case of slot prop type.
 *
 * The placeholder never reaches a real page. PageLayoutPageVariant::build()
 * replaces the whole `main_page_content` source node with the render array
 * from ::setMainContent() before any source plugin runs, so it only appears
 * where no page is being rendered: the builder, and the page layout's own
 * Preview.
 *
 * @see \Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant::replaceTitleAndContent()
 */
#[Source(
  id: 'main_page_content',
  label: new TranslatableMarkup('[Page] Main content'),
  description: new TranslatableMarkup('The Drupal `Main page content` block (system_main_block).'),
  prop_types: ['slot'],
  tags: [],
  context_requirements: ['page'],
  context_definitions: [
    'page' => new ContextDefinition('uri', label: new TranslatableMarkup('Page'), required: TRUE),
  ]
)]
class MainPageContentSource extends PageRegionSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function regionLabel(): TranslatableMarkup {
    return new TranslatableMarkup('Main content');
  }

  /**
   * {@inheritdoc}
   *
   * The single most important region on the page, so it gets the room.
   */
  protected function regionSize(): string {
    return 'lg';
  }

}
