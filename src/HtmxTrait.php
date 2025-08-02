<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Url;

/**
 * Trait for handling HTMX attributes and rendering.
 */
trait HtmxTrait {

  /**
   * Wrap a renderable array for out-of-band swapping.
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
  protected function addOutOfBand(array $renderable, string $target_selector, string $swap): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'hx-swap-oob' => $swap . ':' . $target_selector,
      ],
      'content' => $renderable,
    ];
  }

  /**
   * Alter a renderable array for out-of-band swapping.
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
  protected function makeOutOfBand(array $renderable, string $target_selector, string $swap): array {
    $renderable['#attributes']['hx-swap-oob'] = $swap . ':' . $target_selector;

    return $renderable;
  }

  /**
   * Adds a target and swap method to an array of attributes.
   *
   * @param array $attr
   *   The array of attributes to modify.
   * @param string $target
   *   (Optional) The target selector. Defaults to 'this'.
   *
   * @return array
   *   The modified array of attributes.
   */
  protected function addTarget(array $attr, string $target = 'this'): array {
    $attr['hx-target'] = $target;
    $attr['hx-swap'] = 'outerHTML';

    return $attr;
  }

  /**
   * Sets the trigger and method for an HTMX request.
   *
   * @param string $trigger
   *   The trigger event for the request.
   * @param string $method
   *   The HTTP method to use.
   * @param \Drupal\Core\Url $url
   *   The URL for the request.
   *
   * @return array
   *   An array of attributes for the HTMX request.
   */
  protected function setTrigger(string $trigger, string $method, Url $url): array {
    return [
      'hx-' . $method => $url->toString(),
      'hx-trigger' => $trigger,
      // Most HTMX responses will use OOB swaps so let's de activate swapping by
      // default.
      'hx-swap' => 'none',
    ];
  }

}
