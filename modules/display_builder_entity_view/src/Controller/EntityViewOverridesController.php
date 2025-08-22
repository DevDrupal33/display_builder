<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to get access the Display Builder admin UI.
 *
 * @internal
 *   Controller classes are internal.
 */
final class EntityViewOverridesController extends ControllerBase {

  public function __construct(
    protected StateManagerInterface $stateManager,
  ) {}

  /**
   * Renders the Layout UI for override entities.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the entity view display is not found or not overridable.
   *
   * @return array
   *   A render array containing the display builder.
   */
  public function getBuilder(RouteMatchInterface $route_match): array {
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore

    $entity_type_id = $route_match->getParameter('entity_type_id');
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);

    $view_mode = $route_match->getParameter('view_mode_name');

    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $entity_display */
    $entity_display = $this->getEntityViewDisplay($entity_type_id, $entity->bundle(), $view_mode);
    \assert($entity_display instanceof DisplayBuilderOverridableInterface);
    /** @var \Drupal\display_builder\WithDisplayBuilderInterface $with_display_builder */
    $with_display_builder = $entity->get($entity_display->getDisplayBuilderOverrideField());

    if (!$with_display_builder) {
      // Display Builder is not activated for this entity view display.
      throw new NotFoundHttpException();
    }

    $builder_instance_id = $with_display_builder->getInstanceId();

    if (!$this->stateManager->load($builder_instance_id)) {
      // Display Builder instance was not created yet or deleted, create it on
      // the fly.
      $with_display_builder->initInstanceIfMissing();
    }

    // We build the rendered page.
    $contexts = $this->stateManager->getContexts($builder_instance_id);
    $display_builder = $with_display_builder->getDisplayBuilder();

    return $display_builder->build($builder_instance_id, $contexts);
  }

  /**
   * Redirects to the default route of a specified component.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object that redirects to the first available builder.
   */
  public function getFirstBuilder(RouteMatchInterface $route_match): Response {
    $entity_type_id = $route_match->getParameter('entity_type_id');
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    $account = $this->currentUser();
    $view_mode = $this->getFirstOverridableViewMode($entity, $account);

    if ($view_mode) {
      $route = "entity.{$entity_type_id}.display_builder.{$view_mode}";
      $url = Url::fromRoute($route, [$entity_type_id => $entity->id()]);
      $response = new TrustedRedirectResponse($url->toString());

      return $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Access callback to ensure display tab belongs to current bundle.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $route = $route_match->getRouteObject();

    if (!$route) {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }
    $entity_type_id = $route->getDefault('entity_type_id');
    $entity = $route_match->getParameter($entity_type_id);
    $view_mode_name = $route->getDefault('view_mode_name');

    return $this->overrideBuilderAccessResult($account, $entity, $view_mode_name);
  }

  /**
   * Access callback for the main display.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkFirstBuilderAccess(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $entity_type_id = $route_match->getParameter('entity_type_id');
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $route_match->getParameter($entity_type_id);
    $view_mode = $this->getFirstOverridableViewMode($entity, $account);

    if ($view_mode) {
      return AccessResult::allowed()->addCacheContexts(['route']);
    }

    return AccessResult::forbidden()->addCacheContexts(['route']);
  }

  /**
   * Get the first overridable view mode of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return string|null
   *   The first overridable view mode name, or NULL if none is found.
   */
  protected function getFirstOverridableViewMode(EntityInterface $entity, AccountInterface $account): ?string {
    $display_infos = EntityViewDisplay::getDisplayInfos($this->entityTypeManager());
    $view_modes = $display_infos[$entity->getEntityTypeId()]['bundles'][$entity->bundle()] ?? [];

    foreach (\array_keys($view_modes) as $view_mode) {
      $access = $this->overrideBuilderAccessResult($account, $entity, (string) $view_mode);

      if ($access->isAllowed()) {
        return (string) $view_mode;
      }
    }

    return NULL;
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
   * @return \Drupal\display_builder\WithDisplayBuilderInterface|null
   *   The corresponding entity view display.
   */
  protected function getEntityViewDisplay(string $entity_type_id, string $bundle, string $view_mode): ?WithDisplayBuilderInterface {
    $display_id = \sprintf('%s.%s.%s', $entity_type_id, $bundle, $view_mode);

    /** @var \Drupal\display_builder\WithDisplayBuilderInterface|null $display */
    $display = $this->entityTypeManager()->getStorage('entity_view_display')
      ->load($display_id);

    return $display;
  }

  /**
   * Returns AccessResult for given entity and view mode.
   *
   * Any user with both the permission to edit the content and the one to use
   * the display builder profile can override the display.
   * The permission to edit the content is already checked with _entity_access
   * in DisplayBuilderRoutes so we just need to check the profile permission.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check access for.
   * @param string|null $view_mode_name
   *   The view mode name, or NULL if not applicable.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function overrideBuilderAccessResult(AccountInterface $account, EntityInterface $entity, ?string $view_mode_name): AccessResultInterface {
    $forbidden = AccessResult::forbidden()->addCacheContexts(['route']);

    if ($view_mode_name === NULL) {
      return $forbidden;
    }

    $display = self::getEntityViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $view_mode_name);

    if (!$display instanceof DisplayBuilderOverridableInterface) {
      return $forbidden;
    }

    if (!$display->isDisplayBuilderOverridable()) {
      return $forbidden;
    }

    $permission = $display->getDisplayBuilderOverrideProfile()->getPermissionName();

    // This is the expected check.
    if (!$account->hasPermission($permission)) {
      return $forbidden;
    }

    return AccessResult::allowed()->addCacheableDependency($display)->addCacheContexts(['route']);
  }

}
