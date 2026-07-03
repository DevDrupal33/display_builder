<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Routing;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ServiceCollectionInterface;

/**
 * Provides routes for the Display Builder Views.
 *
 * @internal
 *   Tagged services are internal.
 */
final class DisplayBuilderRoutes implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    #[AutowireLocator('paramconverter')]
    private ServiceCollectionInterface $converters,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RoutingEvents::ALTER => 'onAlterRoutes',
    ];
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function onAlterRoutes(RouteBuildEvent $event): void {
    // From Drupal 11.3 to Drupal 11.4, converter route parameter has changed.
    // The value in routing.yml is now the 11.4 one by default.
    // Restore the 11.3 one if the 11.4 is not available.
    // @see https://www.drupal.org/project/drupal/issues/3436295
    // @todo Remove once Drupal 11.3 is not supported.
    if ($this->converters->has('paramconverter.views_ui')) {
      return;
    }
    /** @var \Symfony\Component\Routing\RouteCollection $collection */
    $collection = $event->getRouteCollection();
    $route = $collection->get('display_builder_views.views.manage');
    $parameters = $route->getOption('parameters');
    $parameters['view']['converter'] = 'drupal.proxy_original_service.paramconverter.views_ui';
    $route->setOption('parameters', $parameters);
    $collection->add('display_builder_views.views.manage', $route);
  }

}
