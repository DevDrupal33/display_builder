<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Strips admin chrome from a Display Builder live-preview page.
 *
 * The live preview loads a front-end page inside an iframe (@see
 * \Drupal\display_builder\Plugin\display_builder\Island\PreviewPanel). Viewed
 * as an editor, that page carries the admin toolbar / navigation, which is
 * irrelevant to a preview and misrepresents what a visitor actually sees - so
 * it is removed on the isolated-preview route.
 */
class LivePreviewChrome {

  /**
   * Page-top keys added by the admin chrome modules.
   *
   * - 'toolbar': core Toolbar (and Admin Toolbar contrib, which alters it).
   * - 'navigation' / 'top_bar': core Navigation.
   */
  private const CHROME_KEYS = ['toolbar', 'navigation', 'top_bar'];

  public function __construct(
    protected RequestStack $requestStack,
  ) {}

  /**
   * Removes admin chrome from a live-preview page.
   *
   * Done in preprocess rather than in a hook_page_top() implementation because
   * page_top has no alter hook and core Navigation pins its own implementation
   * with Order::Last, which would re-add the chrome after any earlier removal.
   * By preprocess the page_top render array is fully built, so removing the
   * keys here is independent of hook order.
   *
   * @param array $variables
   *   The html template variables, passed by reference.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $request = $this->requestStack->getCurrentRequest();

    if (!$request || $request->attributes->get('_route') !== 'display_builder.preview_island') {
      return;
    }

    if (!isset($variables['page_top']) || !\is_array($variables['page_top'])) {
      return;
    }

    foreach (self::CHROME_KEYS as $key) {
      unset($variables['page_top'][$key]);
    }
  }

}
