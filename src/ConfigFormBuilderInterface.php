<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for the config form builder.
 */
interface ConfigFormBuilderInterface {

  /**
   * Build form for integration with Display Builder.
   *
   * @param EntityWithDisplayBuilderInterface $entity
   *   An entity allowing the use of Display Builder.
   * @param bool $mandatory
   *   (Optional). Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts). If not mandatory, the Display Builder is activated only
   *   if a Display Builder config entity is selected.
   *
   * @return array
   *   A form renderable array.
   */
  public function build(EntityWithDisplayBuilderInterface $entity, bool $mandatory = TRUE): array;

}
