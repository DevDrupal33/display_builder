<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for display_builder.
 */
class LibrariesSwitcher {

  private const STATE_STORAGE_KEY = 'display_builder.asset_libraries_local';

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Switch libraries according to environment.
   *
   * @param array $libraries
   *   An associative array of libraries, passed by reference. The array key
   *   for any particular library will be the name from *.libraries.yml.
   * @param string $extension
   *   Can either be 'core' or the machine name of the extension that registered
   *   the libraries.
   */
  #[Hook('library_info_alter')]
  public function switchLibraries(array &$libraries, string $extension): void {
    if ($extension !== 'display_builder') {
      return;
    }

    // By default, libraries are retrieved from CDN. Switch to local mode with
    // a state value.
    // This is needed for tests or disconnected environments.
    $is_local = $this->state->get(self::STATE_STORAGE_KEY, FALSE);

    if (!$is_local) {
      return;
    }

    if (isset($libraries['shoelace']['dependencies'])) {
      $libraries['shoelace']['dependencies'] = \array_filter(
        $libraries['shoelace']['dependencies'],
        static fn ($dependency) => $dependency !== 'display_builder/_shoelace_cdn'
      );
      $libraries['shoelace']['dependencies'][] = 'display_builder/_shoelace_local';
    }

    if (isset($libraries['htmx_sse']['dependencies'])) {
      $libraries['htmx_sse']['dependencies'] = \array_filter(
        $libraries['htmx_sse']['dependencies'],
        static fn ($dependency) => $dependency !== 'display_builder/_htmx_sse_cdn'
      );
      $libraries['htmx_sse']['dependencies'][] = 'display_builder/_htmx_sse_local';
    }
  }

}
