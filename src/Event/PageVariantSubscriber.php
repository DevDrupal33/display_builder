<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Decides which pages Display Builder renders blank.
 *
 * Two cases get the bare 'display_builder_full' variant - no theme
 * page.html.twig, no Page Layout around them:
 *
 * - Any route opting in with the route option below: the builder UI itself,
 *   which draws its own chrome and would otherwise sit inside the themed admin
 *   page, shrinking only the preview pane on a viewport switch.
 * - The isolated live preview, but only when the previewed instance itself
 *   says it should not be wrapped in chrome. Everything else previews inside
 *   the page wrapper the site would really give it.
 *
 *   @see ::onSelectPageDisplayVariant()
 *   @see \Drupal\display_builder\DisplayBuildableInterface::previewWithChrome()
 *
 * Lives in the main module rather than in Display Builder Page Layout because
 * the entity view, Views and isolated-preview routes all rely on it, on sites
 * that never install Page Layout.
 *
 * @code
 * route.name:
 *  ...
 *  options:
 *    _admin_route: false
 *    _display_builder_full_page_route: true
 *
 * @endcode
 */
class PageVariantSubscriber implements EventSubscriberInterface {

  /**
   * The route serving the live-preview iframe.
   */
  private const PREVIEW_ROUTE = 'display_builder.preview_island';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Below Page Layout's own subscriber (-100), which picks the layout
      // variant for every non-admin route. Selecting a variant does not stop
      // propagation, so running last is what makes this decision the final one
      // rather than a race with that subscriber's registration order.
      // @see \Drupal\display_builder_page_layout\EventSubscriber\PageVariantSubscriber
      RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => [
        ['onSelectPageDisplayVariant', -200],
      ],
    ];
  }

  /**
   * Selects the page display variant.
   *
   * The live preview is the interesting half. Whether it gets the site's
   * chrome (header, footer, region blocks) around it, or renders bare, is the
   * previewed instance's own answer to
   * \Drupal\display_builder\DisplayBuildableInterface::previewWithChrome() -
   * a per-instance decision, since the same buildable can be a page-worthy
   * fragment for one entity type and view mode and a bare one for another.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    $route_match = $event->getRouteMatch();

    if ($route_match->getRouteObject()?->getOption('_display_builder_full_page_route')) {
      $event->setPluginId('display_builder_full');

      return;
    }

    if ($route_match->getRouteName() !== self::PREVIEW_ROUTE) {
      return;
    }
    $instance = $route_match->getParameter('display_builder_instance');

    if ($instance instanceof InstanceInterface && !$instance->previewWithChrome()) {
      $event->setPluginId('display_builder_full');
    }
  }

}
