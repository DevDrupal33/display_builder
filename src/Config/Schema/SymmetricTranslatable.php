<?php

declare(strict_types=1);

namespace Drupal\display_builder\Config\Schema;

use Drupal\Core\Config\Schema\Sequence;

/**
 * Defines a configuration element of type Sequence.
 */
class SymmetricTranslatable extends Sequence {

  /**
   * Determines if there is a translatable value.
   *
   * @return bool
   *   Returns true if a translatable element is found.
   */
  public function hasTranslatableElements(): bool {
    // @todo something?
    return parent::hasTranslatableElements();
  }

}
