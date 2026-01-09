<?php

declare(strict_types=1);

namespace Drupal\display_builder\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Theme negotiator for Display Builder preview routes.
 *
 * Always uses the default frontend theme for preview rendering.
 */
class PreviewThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * Constructs a PreviewThemeNegotiator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    $route_name = $route_match->getRouteName();

    if (!$route_name) {
      return FALSE;
    }

    // Apply only to preview routes.
    return $route_name === 'display_builder.preview';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    // Always use the default frontend theme for previews.
    return $this->configFactory->get('system.theme')->get('default');
  }

}

