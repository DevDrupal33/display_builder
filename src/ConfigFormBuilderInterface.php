<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Interface for the config form builder.
 */
interface ConfigFormBuilderInterface {

  // Storage property for the profile config entity ID.
  public const PROFILE_PROPERTY = 'display_builder';

  // Storage property for the nestable list of UI Patterns 2 sources.
  public const SOURCES_PROPERTY = 'sources';

  /**
   * Build form for integration with Display Builder.
   *
   * @param \Drupal\display_builder\WithDisplayBuilderInterface $entity
   *   An entity allowing the use of Display Builder.
   * @param bool $mandatory
   *   (Optional). Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts). If not mandatory, the Display Builder is activated only
   *   if a Display Builder config entity is selected.
   *
   * @return array
   *   A form renderable array.
   */
  public function build(WithDisplayBuilderInterface $entity, bool $mandatory = TRUE): array;

}
