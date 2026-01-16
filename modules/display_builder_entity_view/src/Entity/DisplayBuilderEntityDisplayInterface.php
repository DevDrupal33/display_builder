<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Provides an interface for entity displays that have Display Builder.
 */
interface DisplayBuilderEntityDisplayInterface extends EntityViewDisplayInterface {

  /**
   * Determines if Display Builder is enabled.
   *
   * @return bool
   *   TRUE if Display Builder is enabled, FALSE otherwise.
   */
  public function isDisplayBuilderEnabled();

  /**
   * Initial import from existing data.
   *
   * @return array
   *   List of UI Patterns sources.
   *
   * @see EntityView::initInstanceIfMissing()
   */
  public function initialImport(): array;

}
