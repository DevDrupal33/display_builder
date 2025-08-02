<?php

declare(strict_types=1);

namespace Drupal\display_builder\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for display builder entities.
 */
class DisplayBuilderRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    if ($add_page_route = $this->getEditPluginFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.edit_plugin_form", $add_page_route);
    }

    return $collection;
  }

  /**
   * Gets the edit-plugin-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEditPluginFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('edit-plugin-form')
      && \is_string($entity_type->getLinkTemplate('edit-plugin-form'))) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('edit-plugin-form'));
      // Use the edit form handler, if available, otherwise default.
      $operation = 'default';

      if ($entity_type->getFormClass('edit-plugin')) {
        $operation = 'edit-plugin';
      }
      $route
        ->setDefaults([
          '_entity_form' => "{$entity_type_id}.{$operation}",
          '_title_callback' => '\Drupal\display_builder\Form\DisplayBuilderIslandPluginForm::editFormTitle',
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.update")
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      // Entity types with serial IDs can specify this in their route
      // requirements, improving the matching process.
      if ($this->getEntityTypeIdKeyType($entity_type) === 'integer') {
        $route->setRequirement($entity_type_id, '\d+');
      }

      return $route;
    }

    return NULL;
  }

}
