<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for island plugins.
 */
interface IslandInterface extends ConfigurableInterface, ContainerFactoryPluginInterface, IslandEventSubscriberInterface, PluginInspectionInterface {

  /**
   * Define keyboard shortcuts.
   *
   * @return array
   *   An associative array of key => description.
   */
  public static function keyboardShortcuts(): array;

  /**
   * Build renderable from state data.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param array $data
   *   (Optional) UI Patterns 2 sources data. It can be the full data state
   *   (so, the same as $builder->getCurrentState()) or just some specific data
   *   of a single source of a sub-tree of sources.
   * @param array $options
   *   (Optional) Additional data to alter the island rendering.
   *
   * @return array
   *   A renderable array.
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array;

  /**
   * Alter form element after its built.
   *
   * @param array $element
   *   An associative array containing the structure of the form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The altered form element.
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array;

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The island label.
   */
  public function label(): string;

  /**
   * Get island type value.
   *
   * @return string
   *   The type value attribute value.
   */
  public function getTypeId(): string;

  /**
   * Get HTML ID.
   *
   * This ID is used to update an interactive island.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return string
   *   The HTML ID attribute value.
   */
  public function getHtmlId(string $builder_id): string;

  /**
   * Returns the icon if any.
   *
   * @return string
   *   The icon string.
   */
  public function getIcon(): ?string;

  /**
   * Determine if the Island plugin is applicable.
   *
   * @return bool
   *   TRUE if plugin is applicable, FALSE otherwise.
   */
  public function isApplicable(): bool;

}
