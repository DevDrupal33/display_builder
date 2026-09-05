<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\RenderableBuilderTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Display builder routes.
 */
class ApiPublishingController extends ApiControllerBase {

  use RenderableBuilderTrait;

  /**
   * Publish display builder instance.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   */
  public function publish(Request $request, InstanceInterface $display_builder_instance): array {
    $display_builder_instance->publish();

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_PUBLISH);
  }

  /**
   * Restore to last published state.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   */
  public function restore(Request $request, InstanceInterface $display_builder_instance): array {
    // The UI only offers Restore once something is published, so this is a
    // direct POST. Nothing was restored; do not report it done.
    if (!$display_builder_instance->restore()) {
      return $this->buildError(
        (string) $display_builder_instance->id(),
        $this->t('Nothing is published yet, so there is nothing to restore.'),
        TRUE,
      );
    }

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_RESTORE);
  }

  /**
   * Revert entity override to default display.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP Request.
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array
   */
  public function revert(Request $request, InstanceInterface $display_builder_instance): array {
    // The UI only offers Revert on an override, so this is a direct POST.
    // Nothing was reverted; do not report it done.
    if (!$display_builder_instance->revert()) {
      return $this->buildError(
        (string) $display_builder_instance->id(),
        $this->t('Only an entity view override can be reverted.'),
        TRUE,
      );
    }

    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_REVERT);
  }

}
