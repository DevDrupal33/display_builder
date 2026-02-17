<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared logic between integrations controllers.
 */
abstract class IntegrationControllerBase extends ControllerBase {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

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

    if (!$profile->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $instance_id = $buildable->getInstanceId();
    $buildable->initInstanceIfMissing();
    $view_builder = $this->entityTypeManager()->getViewBuilder('display_builder_profile');

    return $view_builder->view($profile, $instance_id);
  }

}
