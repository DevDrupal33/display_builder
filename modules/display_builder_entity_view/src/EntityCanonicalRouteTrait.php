<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view;

use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Detects whether an entity type and view mode render as a full page.
 *
 * Backs ::previewWithChrome() for the two entity view buildables.
 *
 * @see \Drupal\display_builder\DisplayBuildableInterface::previewWithChrome()
 */
trait EntityCanonicalRouteTrait {

  /**
   * Whether a display of this entity type and view mode is a full page.
   *
   * Node, taxonomy term and user canonical routes are built by
   * DefaultHtmlRouteProvider, which always renders the entity through
   * '_entity_view' - genuinely rendering it as a page. Comment's canonical
   * route is a hand-written redirect to the node it belongs to, with no such
   * default: it has a canonical link, but visiting it never renders the
   * comment as a page. Media's canonical link template is the edit-form path
   * unless the 'standalone_url' setting is on, so it only gets a real
   * '_entity_view' route then. Checking the route shape, rather than a fixed
   * entity type list, is what makes all three fall out for free.
   *
   * The route always renders the 'full' view mode; 'default' is only its
   * stand-in when the bundle has no distinct, enabled 'full' display of its
   * own - once one exists, 'default' is never what the canonical route shows,
   * so it does not get chrome either. Every other view mode (teaser, a custom
   * mode) is a fragment that never appears as a page by itself.
   *
   * @param string $entity_type_id
   *   The entity type the display belongs to.
   * @param string $bundle
   *   The bundle the display belongs to.
   * @param string $view_mode
   *   The view mode machine name.
   *
   * @return bool
   *   TRUE when this display previews as a full page.
   */
  protected function previewsFullPage(string $entity_type_id, string $bundle, string $view_mode): bool {
    if (!\in_array($view_mode, ['full', 'default'], TRUE)) {
      return FALSE;
    }

    try {
      $route = \Drupal::service('router.route_provider')->getRouteByName("entity.{$entity_type_id}.canonical");
    }
    catch (RouteNotFoundException) {
      return FALSE;
    }

    if (!$route->hasDefault('_entity_view')) {
      return FALSE;
    }

    if ($view_mode === 'default') {
      $full_display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load("{$entity_type_id}.{$bundle}.full");

      return $full_display === NULL || !$full_display->status();
    }

    return TRUE;
  }

}
