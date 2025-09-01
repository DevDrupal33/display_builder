<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\display_builder\WithDisplayBuilderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared logic between integrations controllers.
 */
abstract class IntegrationControllerBase extends ControllerBase {

  /**
   * Render a Display Builder profile entity view.
   *
   * @param \Drupal\display_builder\WithDisplayBuilderInterface $buildable
   *   The integration using the display builder.
   *
   * @return array
   *   A renderable array
   */
  protected function renderBuilder(WithDisplayBuilderInterface $buildable): array {
    /** @var \Drupal\display_builder\DisplayBuilderInterface $profile */
    $profile = $buildable->getDisplayBuilder();

    if (!$profile) {
      throw new NotFoundHttpException();
    }

    $instance_id = $buildable->getInstanceId();
    $buildable->initInstanceIfMissing();
    $view_builder = $this->entityTypeManager()->getViewBuilder('display_builder');

    return $view_builder->view($profile, $instance_id);
  }

}
