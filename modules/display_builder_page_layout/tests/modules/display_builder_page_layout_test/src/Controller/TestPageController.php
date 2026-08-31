<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout_test\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Serves the pages the test page layouts are bound to.
 *
 * A page layout creates no route, it repaints a page Drupal already serves.
 * Without this route the fixture paths would be repainted 404 responses: the
 * layout still renders, but the status stays 404 and the main content is the
 * "page not found" message, so a test could not tell a working layout from a
 * broken one. One route covers every '/test/*' fixture path, which makes them
 * ordinary pages answering 200. The name is a whole path segment because a
 * placeholder sharing a segment with static text never matches in Drupal.
 *
 * @see \Drupal\Core\Routing\RouteCompiler::getPatternOutline()
 * @see \Drupal\display_builder_page_layout\EventSubscriber\PageVariantSubscriber
 */
final class TestPageController {

  use StringTranslationTrait;

  /**
   * Builds the main content of a test page.
   *
   * @param string $name
   *   The path suffix the page layout condition matches on.
   *
   * @return array
   *   A renderable array.
   */
  public function build(string $name): array {
    return [
      '#markup' => $this->t('Page layout test page: @name', ['@name' => $name]),
    ];
  }

  /**
   * Builds a page title that is a render array, not a string.
   *
   * @return array
   *   A renderable array.
   */
  public function arrayTitle(): array {
    return [
      '#type' => 'link',
      '#title' => $this->t('Linked page title'),
      '#url' => Url::fromRoute('<front>'),
    ];
  }

}
