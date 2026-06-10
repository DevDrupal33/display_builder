<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Display builder routes.
 */
class ApiPublishingController extends ApiControllerBase {

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
    $display_builder_instance->save();

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
    $display_builder_instance->restore();
    $display_builder_instance->save();

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
    $this->builder = $display_builder_instance;

    return $this->dispatchDisplayBuilderEvent(DisplayBuilderEvents::ON_REVERT);
  }

}
