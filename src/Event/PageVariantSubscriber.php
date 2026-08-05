<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
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
 * - The isolated live preview, but only of a buildable that already renders a
 *   whole page. Everything else previews inside the page wrapper the site would
 *   really give it. @see ::onSelectPageDisplayVariant()
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

  public function __construct(
    private DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

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
   * The live preview is the interesting half. An entity view or a Views display
   * is a fragment: on the real site it appears inside a page, so its preview is
   * left to the normal variant selection and comes out wrapped in whichever
   * Page Layout matches - or, failing that, the theme's page template. That is
   * the point of the preview, to be close to the real render.
   *
   * A page layout is not a fragment. It draws the header and the footer itself,
   * so wrapping its preview in another page would show both twice.
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

    if ($route_match->getRouteName() === self::PREVIEW_ROUTE && $this->rendersFullPage($route_match)) {
      $event->setPluginId('display_builder_full');
    }
  }

  /**
   * Tells whether the previewed instance is a whole page by itself.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match, carrying the previewed instance.
   *
   * @return bool
   *   TRUE when the buildable behind the instance renders a full page.
   *
   * @see \Drupal\display_builder\Attribute\DisplayBuildable::$renders_full_page
   */
  private function rendersFullPage(RouteMatchInterface $route_match): bool {
    $instance = $route_match->getParameter('display_builder_instance');

    if (!$instance instanceof InstanceInterface) {
      return FALSE;
    }
    $instance_id = (string) $instance->id();

    foreach ($this->displayBuildableManager->getDefinitions() as $definition) {
      if (\str_starts_with($instance_id, $definition['instance_prefix'])) {
        return (bool) ($definition['renders_full_page'] ?? FALSE);
      }
    }

    // An instance with no provider (a demo, a test) is not a page.
    return FALSE;
  }

}
