<?php

declare(strict_types=1);

namespace Drupal\display_builder;

/**
 * Island plugin manager toolbar button interface.
 */
interface IslandPluginToolbarButtonConfigurationInterface extends IslandConfigurationFormInterface {

  /**
   * Shows if the label is enabled for a given button.
   *
   * @param string $button_id
   *   The button ID.
   *
   * @return bool
   *   TRUE if the label is enabled, FALSE otherwise.
   */
  public function showLabel(string $button_id): bool;

  /**
   * Shows if the label is enabled for a given button.
   *
   * @param string $button_id
   *   The button ID.
   *
   * @return bool
   *   TRUE if the label is enabled, FALSE otherwise.
   */
  public function showIcon(string $button_id): bool;

  /**
   * Returns the list of buttons provided by this plugin.
   *
   * Each button is an array with two keys: 'label' and 'icon', both boolean.
   * The keys indicate if the label or icon can be configured to be shown.
   * For example:
   *
   * @code
   * return [
   *  'my_button_id' => ['label' => TRUE, 'icon' => FALSE],
   * 'my_other_button_id' => ['label' => TRUE, 'icon'=> TRUE],
   * ];
   *
   * @endcode
   *
   * @return array
   *   An associative array of button IDs and their configuration options.
   */
  public function hasButtons(): array;

}
