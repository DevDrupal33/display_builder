<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

/**
 * Interface for renderable alterers.
 */
interface RenderableAltererInterface extends IslandInterface {

  /**
   * Alters the given element with additional data.
   *
   * @param array $element
   *   The element to be altered.
   * @param array $data
   *   (Optional) Additional data to be used for alteration.
   *
   * @return array
   *   The altered element.
   */
  public function alterElement(array $element, array $data = []): array;

}
