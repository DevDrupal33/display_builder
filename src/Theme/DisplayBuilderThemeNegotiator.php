<?php

declare(strict_types=1);

namespace Drupal\display_builder\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Theme negotiator for Display Builder backend routes.
 *
 * Uses the configured backend_theme from the profile, or falls back
 * to the admin theme.
 */
class DisplayBuilderThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * Constructs a DisplayBuilderThemeNegotiator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    $route = $route_match->getRouteObject();

    if (!$route) {
      return FALSE;
    }

    // Check if this is a Display Builder route (but not the preview route).
    $route_name = $route_match->getRouteName();

    if (!$route_name) {
      return FALSE;
    }

    // Apply to Display Builder admin routes, excluding preview routes.
    if (\str_starts_with($route_name, 'display_builder.') && !\str_contains($route_name, 'preview')) {
      return TRUE;
    }

    // Also apply to entity view display builder routes.
    if (\str_starts_with($route_name, 'display_builder_entity_view.')) {
      return TRUE;
    }

    // Apply to page layout routes.
    if (\str_starts_with($route_name, 'display_builder_page_layout.')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    // Try to get the profile from route parameters.
    $profile = $route_match->getParameter('display_builder_profile');

    if (\is_string($profile)) {
      $profile = $this->entityTypeManager
        ->getStorage('display_builder_profile')
        ->load($profile);
    }

    // If we have a profile with a configured backend theme, use it.
    if ($profile && $profile->getBackendTheme()) {
      return $profile->getBackendTheme();
    }

    // Otherwise, fall back to the admin theme.
    return $this->configFactory->get('system.theme')->get('admin') ?: NULL;
  }

}

