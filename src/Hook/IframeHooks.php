<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;

/**
 * Hook implementations for the islands  displayed in an iframe.
 *
 * @see \Drupal\display_builder\Controller\IframeController
 */
class IframeHooks {

  /**
   * Remove the toolbar.
   */
  #[Hook('page_top', order: new OrderAfter(['toolbar']))]
  public function removeToolbar(array &$page_top): void {
    $route_name = \Drupal::routeMatch()->getRouteName();

    if ($route_name === 'display_builder.iframe') {
      unset($page_top['toolbar']);
    }
  }

}
