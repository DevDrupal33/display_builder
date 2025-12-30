<?php

/**
 * @file
 * Describes hooks provided by the Display Builder module.
 *
 * @codeCoverageIgnore
 */

declare(strict_types=1);

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Allows modules to register display builder providers.
 *
 * Display builder providers are classes implementing the
 * \Drupal\display_builder\DisplayBuildableInterface interface.
 *
 * @return array
 *   An associative array of display builder providers, keyed by a unique
 *   machine name. Each provider is an associative array with the following
 *   keys:
 *   - prefix: Must come from ::getPrefix(), used for UI.
 *     @see \Drupal\display_builder\DisplayBuildableInterface::getPrefix()
 *   - class: The class implementing the DisplayBuildableInterface. Used to
 *     call static methods from the interface.
 *   - label: A human-readable name for the provider, used for UI.
 *     @see \Drupal\display_builder\DisplayBuildableInterface
 *   - storage: Storage entity name, used for UI.
 */
function hook_display_builder_provider_info(): array {
  return [
    'views' => [
      // Must come from ::getPrefix().
      // @see \Drupal\display_builder\DisplayBuildableInterface::getPrefix()
      'prefix' => 'my_provider_prefix_',
      // The class implementing the DisplayBuildableInterface.
      // Use to call static methods from the interface.
      // @see \Drupal\display_builder\DisplayBuildableInterface
      'class' => '\Drupal\my_module\MyProviderClass',
      // A human-readable name for the provider, used for UI.
      'label' => new TranslatableMarkup('Views'),
      // Used to load instances in display builder UI.
      'storage' => 'my_storage',
    ],
  ];
}
