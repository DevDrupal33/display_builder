<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\EventSubscriber;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\display_builder\DisplayBuildableInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Remove the _admin_route for specific entity-related routes.
 */
class EntityAdminRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->getEntityTypes() as $entity_type_id => $entity_type) {
      if (!$entity_type->hasFormClasses()) {
        continue;
      }

      foreach ($entity_type->getHandlerClasses()['form'] ?? [] as $handler_id => $form) {
        // $form is a string (namespaced class) here, how do we get an object?
        // $form = \Drupal::formBuilder()->getForm($form);
        if (!$form) {
          continue;
        }

        if (!($form instanceof DisplayBuildableInterface)) {
          continue;
        }

        if (!$form->getProfile()) {
          continue;
        }
        $route_id = \implode('.', ['entity', $entity_type_id, $handler_id]);
        $route = $collection->get($route_id);
        $route->setOption('_admin_route', FALSE);
        $collection->add($route_id, $route);
      }
    }
  }

  /**
   * Returns an array of relevant entity types.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types.
   */
  private function getEntityTypes(): array {
    return \array_filter($this->entityTypeManager->getDefinitions(), static function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(FieldableEntityInterface::class) && $entity_type->hasFormClasses();
    });
  }

}
