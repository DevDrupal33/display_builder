<?php

declare(strict_types=1);

namespace Drupal\display_builder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\RenderableAltererInterface;
use Drupal\ui_patterns\SourceInterface;

/**
 * Hook implementations for display_builder.
 */
class UiPatternsHooks {

  public function __construct(
    protected IslandPluginManagerInterface $islandManager,
  ) {}

  /**
   * Alters the renderable array for a component that has been built.
   *
   * @param mixed &$build
   *   The renderable array of the component.
   * @param \Drupal\ui_patterns\SourceInterface $source
   *   The data array containing information about the component.
   * @param array $source_configuration
   *   The full raw configuration used to build the source.
   */
  #[Hook('ui_patterns_source_value_alter')]
  public function sourceValueAlter(mixed &$build, SourceInterface $source, array &$source_configuration): void {
    if (empty($source_configuration['third_party_settings'])) {
      // Sometimes, third_party_settings are stored as an empty string instead
      // of an empty array.
      return;
    }

    foreach ($source_configuration['third_party_settings'] as $provider => $settings) {
      // In Display Builder, third_party_settings providers can be:
      // - an island plugin ID (our 'normal' way)
      // - a Drupal module name (the Drupal way, found in displays imported and
      // converted, not leveraged by us for now but we may do it later).
      // So, let's check the plugin ID exists before running logic.
      if (!$this->islandManager->hasDefinition($provider)) {
        continue;
      }
      $island = $this->islandManager->createInstance($provider);

      if ($build && $island instanceof RenderableAltererInterface) {
        $build = $island->alterElement($build, $settings);
      }
    }
  }

  /**
   * Add third-party-settings to UI Patterns slot source schema.
   *
   * Can be removed once
   * https://www.drupal.org/project/ui_patterns/issues/3540614 is merged.
   *
   * @param array $definitions
   *   Associative array of configuration type definitions keyed by schema type
   *   names. The elements are themselves array with information about the type.
   */
  #[Hook('config_schema_info_alter')]
  public function schemaInfoAlter(array &$definitions): void {
    $definitions['ui_patterns_slot_source']['mapping']['third_party_settings'] = [
      'type' => 'sequence',
      'sequence' => [
        'type' => 'ui_patterns_slot_source.third_party_setting.[%key]',
      ],
    ];
  }

  /**
   * Implements hook_ui_patterns_source_info_alter().
   *
   * @param array $definitions
   *   An array of all the existing plugin definitions, passed by reference.
   */
  #[Hook('ui_patterns_source_info_alter')]
  public function sourceInfoAlter(array &$definitions): void {
    $definitions['component']['class'] = 'Drupal\display_builder\Plugin\UiPatterns\Source\ComponentSource';
  }

}
