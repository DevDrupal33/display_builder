<?php

declare(strict_types=1);

namespace Drupal\display_builder\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps a preview sub-request out of Dynamic Page Cache, in both directions.
 *
 * A display pinned to a real page previews as that page, rendered in a
 * sub-request (@see
 * \Drupal\display_builder\Controller\ApiPreviewController::renderOnPinnedPage()).
 * Dynamic Page Cache has no main-request guard, and keys on the request rather
 * than on who is rendering it, so without this policy the sub-request would
 * read the ordinary visitor's cached render of that page - the preview would
 * then show the *saved* display and silently ignore every unsaved edit, which
 * is the one thing a live preview must not do.
 *
 * Denying the sub-request also stops the draft render being written to a cache
 * a real visitor could later read from.
 *
 * Internal Page Cache needs no policy: it short-circuits on anything that is
 * not the main request, so it never sees this sub-request at all.
 */
class DenyPreviewSubRequest implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request): ?string {
    return $request->attributes->has(DisplayBuildableInterface::PREVIEW_INSTANCE_ATTRIBUTE)
      ? self::DENY
      : NULL;
  }

}
