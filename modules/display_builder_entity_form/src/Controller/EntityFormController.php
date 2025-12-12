<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Controller;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Controller\IntegrationControllerBase;
use Drupal\display_builder\DisplayBuildableInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to get access the Display Builder admin UI.
 *
 * @internal
 *   Controller classes are internal.
 */
final class EntityFormController extends IntegrationControllerBase {

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
      '@form_mode_name' => $route_match->getParameter('form_mode_name'),
    ];

    return $this->t('Display builder for @bundle, @form_mode_name', $param);
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
    // Builder is on the front theme, render cache is too hard and changes are
    // not working with cache (move something and refresh, previous version
    // will be shown).
    // @todo evaluate with #3529284
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore

    $entity_type_id = $route_match->getParameter('entity_type_id');
    $bundle = $route_match->getParameter('bundle');
    $form_mode = $route_match->getParameter('form_mode_name');

    $entity_display = $this->getEntityFormDisplay($entity_type_id, $bundle, $form_mode);

    if (!$entity_display) {
      // No entity view display.
      throw new NotFoundHttpException();
    }

    return $this->renderBuilder($entity_display);
  }

  /**
   * Get entity view display entity.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   * @param string $bundle
   *   Fieldable entity's bundle.
   * @param string $form_mode
   *   Form mode of the display.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface|null
   *   The corresponding entity form display.
   */
  protected function getEntityFormDisplay(string $entity_type_id, string $bundle, string $form_mode): ?DisplayBuildableInterface {
    $display_id = "{$entity_type_id}.{$bundle}.{$form_mode}";

    /** @var \Drupal\display_builder\DisplayBuildableInterface|null $display */
    $display = $this->entityTypeManager()->getStorage('entity_form_display')
      ->load($display_id);

    return $display;
  }

}
