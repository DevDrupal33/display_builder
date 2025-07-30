<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to get access the Display Builder admin UI.
 *
 * @internal
 *   Controller classes are internal.
 */
final class DisplayBuilderEntityViewController extends ControllerBase {

  public function __construct(
    protected StateManagerInterface $stateManager,
  ) {}

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
   * @return array
   *   A render array.
   */
  public function getBuilder(RouteMatchInterface $route_match): array {
    // Builder is on the front theme, render cache is too hard and changes are
    // not working with cache (move something and refresh, previous version
    // will be shown).
    // @todo fix with #3529284
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore

    $entity_type_id = $route_match->getParameter('entity_type_id');
    $bundle = $route_match->getParameter('bundle');
    $view_mode = $route_match->getParameter('view_mode_name');

    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay $entity_display */
    $entity_display = $this->getEntityViewDisplay($entity_type_id, $bundle, $view_mode);

    /** @var \Drupal\display_builder\DisplayBuilderInterface $display_builder */
    $display_builder = $entity_display->getDisplayBuilder();

    if (!$display_builder) {
      // Display Builder is not activated for this entity view display.
      throw new NotFoundHttpException();
    }

    $builder_id = $entity_display->getInstanceId();

    if (!$this->stateManager->load($builder_id)) {
      // Display Builder instance was not created yet or deleted, create it on
      // the fly.
      $entity_display->initInstanceIfMissing();
    }

    // We build the rendered page.
    $contexts = $this->stateManager->getContexts($builder_id);

    return $display_builder->build($builder_id, $contexts);
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
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The corresponding entity view display.
   */
  protected function getEntityViewDisplay(string $entity_type_id, string $bundle, string $view_mode): EntityViewDisplayInterface {
    $display_id = "{$entity_type_id}.{$bundle}.{$view_mode}";
    $storage = $this->entityTypeManager()->getStorage('entity_view_display');

    return $storage->load($display_id);
  }

}
