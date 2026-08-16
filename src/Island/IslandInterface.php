<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\display_builder\InstanceInterface;

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
   * Get the region this island renders in.
   *
   * Structural, owned by the plugin, never a profile level preference. The
   * value is resolved at discovery, so it is always one of the regions the
   * island type has, or NULL for a type with a single region.
   *
   * @return string|null
   *   The resolved region attribute value.
   *
   * @see \Drupal\display_builder\Island\IslandPluginManager::processDefinition()
   */
  public function getRegionId(): ?string;

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

  /**
   * Alter the renderable of the display builder instance.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   Display builder instance.
   * @param array $build
   *   The renderable to alter.
   *
   * @return array
   *   The altered renderable.
   */
  public function alterRenderable(InstanceInterface $instance, array $build): array;

  /**
   * Whether this island's rebuild may be deferred while it is off screen.
   *
   * Deferrable islands are skipped by the event fan-out when the client
   * reports them as hidden, and fetched again via reload() once the user
   * brings them back into view.
   *
   * An island must decline if its pane content is not produced by its own
   * build() - reload() would then swap in something incomplete. Panels that
   * are permanently on screen have nothing to gain and also decline.
   *
   * @return bool
   *   TRUE if this island can be deferred and later reloaded on its own.
   *
   * @see self::reload()
   * @see \Drupal\display_builder\Event\DisplayBuilderEventsSubscriber::shouldDefer()
   */
  public function isDeferrable(): bool;

  /**
   * Rebuild this island from the instance's current state, out of band.
   *
   * Produces the same out-of-band swap the event fan-out would have produced,
   * but on demand and for this island alone. Used to refresh a panel which was
   * deferred (skipped) while it was hidden, once it becomes visible again.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   Display builder instance.
   *
   * @return array
   *   A renderable array carrying the out-of-band swap.
   *
   * @see \Drupal\display_builder\Controller\ApiController::reloadIsland()
   * @see \Drupal\display_builder\Event\DisplayBuilderEventsSubscriber::shouldDefer()
   */
  public function reload(InstanceInterface $instance): array;

}
