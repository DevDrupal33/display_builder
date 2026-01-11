<?php

declare(strict_types=1);

namespace Drupal\display_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Custom access check for Display Builder internal subrequests.
 *
 * Allows access to the build route if the request is marked as an internal
 * Display Builder subrequest. This marker is set by BuilderPanel when making
 * subrequests for theme-isolated content rendering.
 */
class SubrequestAccessCheck implements AccessInterface {

  /**
   * Checks access for the build route.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request, Route $route) {
    // Allow access only if this is an internal subrequest from BuilderPanel.
    // This attribute is set by BuilderPanel::buildViaSubrequest().
    if ($request->attributes->get('_display_builder_internal') === TRUE) {
      // Mark as uncacheable since this depends on request context.
      return AccessResult::allowed()->setCacheMaxAge(0);
    }

    return AccessResult::forbidden('Access denied: This route is only accessible via internal subrequest.');
  }

}
