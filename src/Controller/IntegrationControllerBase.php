<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\display_builder\DisplayBuildableInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared logic between integrations controllers.
 */
abstract class IntegrationControllerBase extends ControllerBase {

  /**
   * Render a Display Builder profile entity view.
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   The integration using the display builder.
   *
   * @return array
   *   A renderable array
   */
  protected function renderBuilder(DisplayBuildableInterface $buildable): array {
    /** @var \Drupal\display_builder\ProfileInterface $profile */
    $profile = $buildable->getProfile();

    if (!$profile) {
      throw new NotFoundHttpException();
    }

    // Builder is on the front theme, render cache is too hard and changes are
    // not working with cache (move something and refresh, previous version
    // will be shown).
    // @todo evaluate with #3529284
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore
    $instance_id = $buildable->getInstanceId();
    $buildable->initInstanceIfMissing();
    $view_builder = $this->entityTypeManager()->getViewBuilder('display_builder_profile');

    return $view_builder->view($profile, $instance_id);
  }

}
