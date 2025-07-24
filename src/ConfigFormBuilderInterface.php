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

  /**
   * Build 'Display builder' form widget.
   *
   * Temporary method, while EntityWithDisplayBuilderInterface is not already
   * implemented by EntityViewDisplay & Views's DisplayExtender.
   * Will be removed by www.drupal.org/project/display_builder/issues/3534215
   *
   * @param ?string $display_builder
   *   The entity ID of a Display builder config entity.
   * @param bool $mandatory
   *   (Optional). Mandatory.
   *
   * @return array
   *   A form renderable array.
   */
  public function buildDisplayBuilder(?string $display_builder, bool $mandatory = TRUE): array;

}
