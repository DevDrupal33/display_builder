<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Controller;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Controller\IntegrationControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to get access the Display Builder admin UI.
 *
 * @internal
 *   Controller classes are internal.
 */
final class EntityViewController extends IntegrationControllerBase {

  /**
   * Provides a generic title callback for a display used in entities.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the display page.
   */
  public function title(RouteMatchInterface $route_match): TranslatableMarkup {
    $param = [
      '@bundle' => \ucfirst($route_match->getParameter('bundle')),
      '@view_mode_name' => $route_match->getParameter('view_mode_name'),
    ];

    return $this->t('Display builder for @bundle, @view_mode_name', $param);
  }

  /**
   * Renders the Layout UI.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return array
   *   A render array.
   */
  public function getBuilder(RouteMatchInterface $route_match): array {
    $entity_type_id = $route_match->getParameter('entity_type_id');
    $bundle = $route_match->getParameter('bundle');
    $view_mode = $route_match->getParameter('view_mode_name');

    $entity_display = $this->getEntityViewDisplay($entity_type_id, $bundle, $view_mode);

    if (!$entity_display) {
      // No entity view display.
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('entity_view', ['display' => $entity_display]);

    return $this->renderBuilder($buildable);
  }

  /**
   * Get entity view display entity.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   * @param string $bundle
   *   Fieldable entity's bundle.
   * @param string $view_mode
   *   View mode of the display.
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface|null
   *   The corresponding entity view display.
   */
  protected function getEntityViewDisplay(string $entity_type_id, string $bundle, string $view_mode): ?EntityDisplayInterface {
    $display_id = \sprintf('%s.%s.%s', $entity_type_id, $bundle, $view_mode);
    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface|null $display */
    $display = $this->entityTypeManager()->getStorage('entity_view_display')
      ->load($display_id);

    return $display;
  }

}
