<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Lets a source stand in for itself when it has nothing to resolve to.
 *
 * Some sources only mean something inside a real page request: a page region
 * waiting for the route's title, a view area waiting for the view to run. In
 * a builder there is no request, so they render as nothing, and a node that
 * renders as nothing is the one node the user cannot select, move or delete.
 *
 * Two submodules answer that the same way from different upstream bases,
 * which single inheritance leaves no other way to share. What is worth
 * sharing is small but reaches into UI Patterns' prop definitions, and that
 * is exactly the kind of knowledge that should break in one place.
 *
 * @see \Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase
 * @see \Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase
 */
trait RegionPlaceholderSourceTrait {

  use RenderableBuilderTrait;

  /**
   * A named region standing in for what this source cannot resolve.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The region's job, never a plugin ID: "Main content", not
   *   "main_page_content".
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $help
   *   One sentence saying what fills the region on a real page.
   * @param string $size
   *   A CSS class suffix, and a floor rather than a height: 'md' is a strip,
   *   'lg' stands for a page's whole content area.
   *
   * @return mixed
   *   The placeholder, or an empty string for a prop with nowhere to put one.
   */
  protected function buildRegionPlaceholder(string|TranslatableMarkup $label, string|TranslatableMarkup $help, string $size = 'md'): mixed {
    // A string prop has nowhere to put a region, so it gets nothing.
    if (!$this->isSlotProp()) {
      return '';
    }

    return $this->buildPlaceholderRegion($label, $help, $size);
  }

  /**
   * Whether the prop being sourced can hold a region at all.
   *
   * @return bool
   *   TRUE for a slot, FALSE for anything expecting a scalar.
   */
  protected function isSlotProp(): bool {
    return $this->propDefinition['ui_patterns']['type_definition']->getPluginId() === 'slot';
  }

}
