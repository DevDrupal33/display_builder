<?php

/**
 * @file
 * Describes hooks provided by the Display Builder module.
 */

declare(strict_types=1);

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Allows modules to register display builder providers.
 *
 * Display builder providers are classes implementing the
 * \Drupal\display_builder\WithDisplayBuilderInterface interface.
 *
 * @return array
 *   An associative array of display builder providers, keyed by a unique
 *   machine name. Each provider is an associative array with the following
 *   keys:
 *   - label: A human-readable name for the provider.
 *   - class: The class implementing the WithDisplayBuilderInterface. Used to
 *     call static methods from the interface.
 *
 *     @see \Drupal\display_builder\WithDisplayBuilderInterface
 *   - prefix: Must come from ::getPrefix().
 *     @see \Drupal\display_builder\WithDisplayBuilderInterface::getPrefix()
 */
function hook_display_builder_provider_info(): array {
  return [
    'views' => [
      // A human-readable name for the provider.
      'label' => new TranslatableMarkup('Views'),
      // The class implementing the WithDisplayBuilderInterface.
      // Use to call static methods from the interface.
      // @see \Drupal\display_builder\WithDisplayBuilderInterface
      'class' => '\Drupal\my_module\MyProviderClass',
      // Must come from ::getPrefix().
      // @see \Drupal\display_builder\WithDisplayBuilderInterface::getPrefix()
      'prefix' => 'my_provider_prefix_',
    ],
  ];
}
