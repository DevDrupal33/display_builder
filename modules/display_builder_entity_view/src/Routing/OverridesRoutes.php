<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Routing;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\display_builder_entity_view\Controller\EntityViewOverridesController;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for the Display Builder UI.
 *
 * @internal
 *   Tagged services are internal.
 */
final class OverridesRoutes implements EventSubscriberInterface {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    // Run after \Drupal\field_ui\Routing\RouteSubscriber.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -110];

    return $events;
  }

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function onAlterRoutes(RouteBuildEvent $event): void {
    $collection = $event->getRouteCollection();
    $this->buildRoutes($collection);
  }

  /**
   * Build routes for each display overrides.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection to add the routes to.
   */
  private function buildRoutes(RouteCollection $collection): void {
    $display_infos = EntityViewOverride::getDisplayInfos($this->entityTypeManager);

    foreach ($display_infos as $entity_type_id => $display_info) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $canonical = $entity_type->getLinkTemplate('canonical');

      $path = \sprintf('%s/display', $canonical);
      $forward_route = $this->buildSingleRoute($entity_type_id, $path, 'getFirstBuilder', 'checkFirstBuilderAccess');
      $collection->add("entity.{$entity_type_id}.display_builder.forward", $forward_route);

      foreach (\array_keys($display_info['modes']) as $view_mode) {
        $path = \sprintf('%s/display/%s', $canonical, $view_mode);
        $override_route = $this->buildSingleRoute($entity_type_id, $path, 'getBuilder', 'checkAccess');
        $override_route->addDefaults(['view_mode_name' => $view_mode]);
        $collection->add("entity.{$entity_type_id}.display_builder.{$view_mode}", $override_route);
      }
    }
  }

  /**
   * Build single display overrides route.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $path
   *   The path for the route.
   * @param string $controller
   *   The controller method to call.
   * @param string $custom_access
   *   The custom access check method.
   *
   * @return \Symfony\Component\Routing\Route
   *   The built route.
   */
  private function buildSingleRoute(string $entity_type_id, string $path, string $controller, string $custom_access): Route {
    $defaults = [
      '_controller' => EntityViewOverridesController::class . '::' . $controller,
      '_title_callback' => EntityViewOverridesController::class . '::title',
      'entity_type_id' => $entity_type_id,
    ];
    $requirements = [
      '_entity_access' => "{$entity_type_id}.update",
      '_custom_access' => EntityViewOverridesController::class . '::' . $custom_access,
    ];
    $main_options = [
      'parameters' => [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ],
    ];

    return (new Route($path))
      ->setDefaults($defaults)
      ->setRequirements($requirements)
      ->setOptions($main_options);
  }

}
