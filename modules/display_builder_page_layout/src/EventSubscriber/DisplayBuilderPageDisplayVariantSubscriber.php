<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\EventSubscriber;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Drupal should return a display builder page for any non admin route.
 *
 * @code
 * route.name:
 *  ...
 *  options:
 *    _admin_route: true
 *    _display_builder_route: true
 *
 * @endcode
 */
class DisplayBuilderPageDisplayVariantSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    // @todo check if we need to be sooner.
    $events[RenderEvents::SELECT_PAGE_DISPLAY_VARIANT][] = ['onSelectPageDisplayVariant', -100];

    return $events;
  }

  /**
   * Selects the simple page display variant.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    $routeMatch = $event->getRouteMatch();
    $routeObject = $routeMatch->getRouteObject();
    $routeOptions = $routeObject->getOptions();

    // If we are on a display builder page, replace everything with a simple
    // page. Keep compatibility with layout builder.
    $isDisplayBuilderRoute = $routeOptions['_display_builder_route'] ?? FALSE;
    $isLayoutBuilder = $routeOptions['_layout_builder'] ?? FALSE;

    if ($isDisplayBuilderRoute || $isLayoutBuilder) {
      $event->setPluginId('simple_page');

      return;
    }

    $isAdminRoute = $routeOptions['_admin_route'] ?? FALSE;

    if ($isAdminRoute) {
      return;
    }

    // Detect if on a page manager page with display of type full page.
    $isPageDisplayContent = str_contains($routeObject->getDefault('_page_manager_page_variant') ?? '', '-display_builder_page-');

    if ($isPageDisplayContent) {
      $event->setPluginId('simple_page');

      return;
    }

    // Set default to display builder.
    $event->setPluginId('display_builder_page');
  }

}
