<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Display Builder specific HTMX helpers, built on top of core's Htmx class.
 */
final class DisplayBuilderHtmx {

  /**
   * Wraps a renderable array for an HTMX out-of-band swap.
   *
   * @param array $renderable
   *   The renderable array to be prepared.
   * @param string $target_selector
   *   The selector for the target element.
   * @param string $swap
   *   The swap method to use.
   *
   * @return array
   *   The wrapped renderable array.
   */
  public static function outOfBand(array $renderable, string $target_selector, string $swap): array {
    $wrapper = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      'content' => $renderable,
    ];

    (new Htmx())->swapOob($swap . ':' . $target_selector)->applyTo($wrapper);

    return $wrapper;
  }

  /**
   * Alters a renderable array in place for an HTMX out-of-band swap.
   *
   * @param array $renderable
   *   The renderable array to be prepared.
   * @param string $target_selector
   *   The selector for the target element.
   * @param string $swap
   *   The swap method to use.
   *
   * @return array
   *   The altered renderable array.
   */
  public static function makeOutOfBand(array $renderable, string $target_selector, string $swap): array {
    (new Htmx())->swapOob($swap . ':' . $target_selector)->applyTo($renderable);

    return $renderable;
  }

  /**
   * Builds a pre-configured request for a trigger/method/URL combination.
   *
   * Most Display Builder HTMX responses use out-of-band swaps, so the
   * primary swap is deactivated by default. Callers may chain further
   * core Htmx methods (::vals(), ::on(), ::prompt(), ::include(), ...)
   * before calling ::applyTo() on the target render array.
   *
   * @param string $method
   *   The HTTP method: get, post, put, patch or delete.
   * @param \Drupal\Core\Url $url
   *   The URL for the HTMX request.
   * @param string $trigger
   *   The HTMX trigger.
   *
   * @return \Drupal\Core\Htmx\Htmx
   *   A pre-configured Htmx instance, ready for further chaining.
   */
  public static function request(string $method, Url $url, string $trigger): Htmx {
    $htmx = new Htmx();
    match ($method) {
      'get' => $htmx->get($url),
      'post' => $htmx->post($url),
      'put' => $htmx->put($url),
      'patch' => $htmx->patch($url),
      'delete' => $htmx->delete($url),
      default => throw new \InvalidArgumentException(\sprintf('Unsupported HTMX request method "%s".', $method)),
    };
    $htmx->trigger($trigger)->swap('none');

    return $htmx;
  }

}
