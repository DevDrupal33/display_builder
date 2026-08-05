<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Drupal should return a page layout page for any non admin route.
 *
 * Display Builder's own subscriber listens after this one and takes the
 * decision back for the pages it renders blank - the builder UI, and the live
 * preview of a buildable that is already a whole page.
 *
 * @code
 * route.name:
 *  ...
 *  options:
 *    _admin_route: false
 *
 * @endcode
 *
 * @see \Drupal\display_builder\Event\PageVariantSubscriber
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
    // For now we don't build pages for Admin routes, but this will be possible
    // in the future.
    if ($options['_admin_route'] ?? FALSE) {
      return;
    }

    // Fallback to Block Layout if there is no suitable Page Layout entities.
    /** @var \Drupal\display_builder_page_layout\AccessControlHandler $access_control */
    $access_control = $this->entityTypeManager->getAccessControlHandler('page_layout');
    $page_layout = $access_control->loadCurrentPageLayout();

    // In PageLayoutPageVariant we add PageLayout::getCacheTags() to the
    // page renderable but it works only for the pages already managed by
    // Display Builder.
    // So let's add a custom tag for the others.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition('page_layout');
    $event->addCacheTags([$entity_type->getConfigPrefix()]);

    if (!$page_layout) {
      return;
    }

    // Every other pages must be managed by Display Builder.
    $event->setPluginId('display_builder_page_layout');
  }

}
