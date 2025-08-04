<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 *    _admin_route: false
 *    _display_builder_route: true
 *
 * @endcode
 */
class PageVariantSubscriber implements EventSubscriberInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RenderEvents::SELECT_PAGE_DISPLAY_VARIANT => [
        ['onSelectPageDisplayVariant', -100],
      ],
    ];
  }

  /**
   * Selects the page display variant.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    $route = $event->getRouteMatch()->getRouteObject();
    $options = $route->getOptions();

    // In admin pages, we want the page.html.twig from the admin theme.
    // For now we don't build pages for Admin routes, bit this will be possible
    // in the future.
    if ($options['_admin_route'] ?? FALSE) {
      return;
    }

    // When we use Display Builder to build the full page, we don't want to have
    // neither the theme's page.html.twig nor the page managed by Display
    // Builder. We want a simple blank page.
    // Example: entity.page_layout.display_builder.
    if ($options['_display_builder_route'] ?? FALSE) {
      $event->setPluginId('simple_page');

      return;
    }

    // Fallback to Block Layout if there is no suitable Page Layout entities.
    /** @var \Drupal\display_builder_page_layout\AccessControlHandler $access_control */
    $access_control = $this->entityTypeManager->getAccessControlHandler('page_layout');
    $page_layout = $access_control->loadCurrentPageLayout();

    if (!$page_layout) {
      return;
    }

    // Every other pages must be managed by Display Builder.
    $event->setPluginId('display_builder');
  }

}
