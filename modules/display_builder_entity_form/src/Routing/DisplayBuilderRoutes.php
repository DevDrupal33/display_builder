<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Routing;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\display_builder_entity_form\Controller\EntityFormController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for the Display Builder UI.
 *
 * @internal
 *   Tagged services are internal.
 */
final class DisplayBuilderRoutes implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private ModuleHandlerInterface $module_handler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

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
   * Build Display Builder routes for each existing entity view display.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection to add the routes to.
   */
  private function buildRoutes(RouteCollection $collection): void {
    if (!$this->module_handler->moduleExists('field_ui')) {
      return;
    }

    foreach ($this->getEntityTypes() as $entity_type_id => $entity_type) {
      // Try to get the route from the current collection.
      if (!$entity_route = $collection->get($entity_type->get('field_ui_base_route'))) {
        continue;
      }
      $route_name = 'display_builder_entity_form.' . $entity_type_id;
      $route = $this->buildRoute($entity_type, $entity_route);
      $collection->add($route_name, $route);
    }
  }

  /**
   * Build a Display Builder route from an existing Entity View Display route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type for which to build the route.
   * @param \Symfony\Component\Routing\Route $entity_route
   *   The existing entity view display route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The new route for the Display Builder.
   */
  private function buildRoute(EntityTypeInterface $entity_type, Route $entity_route): Route {
    $path = $entity_route->getPath() . '/form-display/{form_mode_name}/display-builder';

    $defaults = [];
    $entity_type_id = $entity_type->id();
    $defaults['entity_type_id'] = $entity_type_id;

    // If the entity type has no bundles and it doesn't use {bundle} in its
    // admin path, use the entity type.
    if (!\str_contains($path, '{bundle}')) {
      if (!$entity_type->hasKey('bundle')) {
        $defaults['bundle'] = $entity_type_id;
      }
      else {
        $defaults['bundle_key'] = $entity_type->getBundleEntityType();
      }
    }

    $requirements = [];
    $requirements['_field_ui_form_mode_access'] = 'administer ' . $entity_type_id . ' display';

    $options = $entity_route->getOptions();
    $options['_admin_route'] = FALSE;

    // @todo add the display builder access check
    // $requirements['_display_builder_access'] = 'view';
    // Trigger the display builder RouteEnhancer.
    $parameters = [];
    // Merge the passed in options in after parameters.
    $options = NestedArray::mergeDeep(['parameters' => $parameters], $options);

    $defaults['_controller'] = EntityFormController::class . '::getBuilder';
    $defaults['_title_callback'] = EntityFormController::class . '::title';
    $route = (new Route($path))->setDefaults($defaults)->setRequirements($requirements)->setOptions($options);

    // Set field_ui.route_enhancer to run on the manage layout form?
    if (isset($defaults['bundle_key'])) {
      $route->setOption('_field_ui', TRUE)->setDefault('bundle', '');
    }

    return $route;
  }

  /**
   * Returns an array of relevant entity types.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types.
   */
  private function getEntityTypes(): array {
    return \array_filter($this->entityTypeManager->getDefinitions(), static function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(FieldableEntityInterface::class) && $entity_type->hasFormClasses() && $entity_type->get('field_ui_base_route');
    });
  }

}
