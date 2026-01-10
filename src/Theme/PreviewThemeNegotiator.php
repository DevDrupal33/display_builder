<?php

declare(strict_types=1);

namespace Drupal\display_builder\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Theme negotiator for Display Builder frontend routes.
 *
 * Always uses the default frontend theme for preview and build rendering.
 * This ensures components are rendered with the correct theme templates.
 */
class PreviewThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * Routes that should use the frontend theme.
   */
  protected const FRONTEND_ROUTES = [
    'display_builder.preview',
    'display_builder.build',
  ];

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

    // Apply to preview and build routes.
    return \in_array($route_name, self::FRONTEND_ROUTES, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    // Always use the default frontend theme.
    return $this->configFactory->get('system.theme')->get('default');
  }

}

