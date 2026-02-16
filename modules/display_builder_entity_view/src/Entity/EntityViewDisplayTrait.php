<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;

/**
 * Common methods for entity view display.
 */
trait EntityViewDisplayTrait {

  /**
   * Calculates dependencies for the display builder.
   *
   * @return $this
   *   The current instance.
   *
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  public function calculateDependencies(): self {
    parent::calculateDependencies();

    if (!$this->displayBuildable()->getInstanceId()) {
      // If there is no instance ID, we cannot calculate dependencies.
      return $this;
    }

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($this->displayBuildable()->getInstanceId());

    if (!$instance) {
      return $this;
    }
    $contexts = $instance->getContexts();

    if (!$contexts) {
      return $this;
    }

    foreach ($this->displayBuildable()->getSources() as $source_data) {
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $source = $this->sourcePluginManager->getSource('', [], $source_data, $contexts);
      $this->addDependencies($source->calculateDependencies());
    }

    return $this;
  }

  /**
   * Is display builder enabled?
   *
   * @return bool
   *   The display builder is enabled if there is a Display Builder entity.
   *
   * @see \Drupal\display_builder_entity_view\DisplayBuilderEntityDisplayInterface
   */
  public function isDisplayBuilderEnabled(): bool {
    // Display Builder must not be enabled for the '_custom' view mode that is
    // used for on-the-fly rendering of fields in isolation from the entity.
    if ($this->getOriginalMode() === static::CUSTOM_MODE) {
      return FALSE;
    }

    return (bool) $this->displayBuildable()->getProfile();
  }

  /**
   * Handler for when dependencies are removed.
   *
   * @param array $dependencies
   *   The dependencies that were removed.
   *
   * @return bool
   *   TRUE if the display can be overridden, FALSE otherwise.
   *
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);

    // Loop through all sources and determine if the removed dependencies are
    // used by their plugins.
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->getInstance();

    // @todo not working when content entity type is deleted.
    if (!$instance) {
      return TRUE;
    }

    $contexts = $instance->getContexts();

    foreach ($this->displayBuildable()->getSources() as $source_data) {
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $source = $this->sourcePluginManager->getSource('', [], $source_data, $contexts);
      $source_dependencies = $source->calculateDependencies();
      $source_removed_dependencies = $this->getPluginRemovedDependencies($source_dependencies, $dependencies);

      if ($source_removed_dependencies) {
        // @todo Allow the plugins to react to their dependency removal in
        // https://www.drupal.org/project/drupal/issues/2579743.
        // $this->removeSource($delta);
        $changed = TRUE;
      }
    }

    return $changed;
  }

  /**
   * Returns the field name used to store overridden displays.
   *
   * @return string|null
   *   The field name used to store overridden displays, or NULL if not set.
   *
   * @see \Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface
   */
  public function getDisplayBuilderOverrideField(): ?string {
    return $this->getThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY);
  }

  /**
   * Returns the display builder override profile.
   *
   * @return \Drupal\display_builder\ProfileInterface|null
   *   The display builder override profile, or NULL if not set.
   *
   * @see \Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface
   */
  public function getDisplayBuilderOverrideProfile(): ?ProfileInterface {
    $display_builder_id = $this->getThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY);

    if ($display_builder_id === NULL) {
      return NULL;
    }

    return $this->loadDisplayBuilder($display_builder_id);
  }

  /**
   * Returns TRUE if the display can be overridden.
   *
   * @return bool
   *   TRUE if the display can be overridden, FALSE otherwise.
   *
   * @see \Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface
   */
  public function isDisplayBuilderOverridable(): bool {
    return !empty($this->getDisplayBuilderOverrideField())
      && $this->getDisplayBuilderOverrideProfile() !== NULL;
  }

  /**
   * Post-save operations for the display builder.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param bool $update
   *   Whether the entity is being updated.
   *
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    if ($profile = $this->displayBuildable()->getProfile()) {
      $this->displayBuildable()->initInstanceIfMissing();

      // Save the profile in the instance if changed.
      $instance = $this->getInstance();
      $profile_id = (string) $profile->id();

      if ($instance && ($instance->getProfile()->id() !== $profile_id)) {
        $instance->setProfile($profile_id);
      }
      $instance->save();
    }

    // Do also overrides.
    if ($profile = $this->getDisplayBuilderOverrideProfile()) {
      $profile_id = (string) $profile->id();
      $storage = $this->entityTypeManager->getStorage('display_builder_instance');

      foreach ($storage->loadMultiple() as $override) {
        /** @var \Drupal\display_builder\InstanceInterface $override */
        if (!$this->isOverrideOfCurrentDisplay($override)) {
          continue;
        }

        if ($override->getProfile()->id() === $profile_id) {
          continue;
        }
        $override->setProfile($profile_id);
        $override->save();
      }
    }

    parent::postSave($storage, $update);
  }

  /**
   * Deletes the display builder instance if it exists.
   *
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  public function delete(): void {
    if ($instance = $this->getInstance()) {
      $instance->delete();
    }
    parent::delete();
  }

  /**
   * Builds a renderable array for the components of a set of entities.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface[] $entities
   *   The entities being displayed.
   *
   * @return array
   *   A renderable array for the entities, indexed by the same keys as the
   *   $entities array parameter.
   *
   * @see \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  public function buildMultiple(array $entities): array {
    $build_list = parent::buildMultiple($entities);

    // If no display builder enabled, stop here and return:
    // - 'Manage Display' build if this trait is used in EntityViewDisplay
    // - 'Layout Builder' build if used in LayoutBuilderEntityViewDisplay.
    if (!$this->isDisplayBuilderEnabled()) {
      // This is also preventing the availability of Display Builder overrides
      // when Display Builder is not used for the entity view display.
      // @todo Is it something we want to keep like that?
      // @see https://www.drupal.org/project/display_builder/issues/3540048
      return $build_list;
    }

    foreach ($entities as $id => $entity) {
      $sources = [];

      if ($this->isDisplayBuilderOverridable()) {
        $display_builder_field = $this->getDisplayBuilderOverrideField();
        $overridden_field = $entity->get($display_builder_field);
        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('entity_view_override', ['field' => $overridden_field]);
        $sources = $buildable->getSources();
      }

      // If the overridden field is empty fallback to the entity view.
      if (\count($sources) === 0) {
        $sources = $this->displayBuildable()->getSources();
      }

      // @see entity.html.twig
      $build_list[$id]['content'] = $this->buildSources($entity, $sources);
    }

    return $build_list;
  }

  /**
   * Check if the instance is overriding this display.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   A list of display builder instances.
   *
   * @return bool
   *   Is the instance overriding this display?
   */
  protected function isOverrideOfCurrentDisplay(InstanceInterface $instance): bool {
    $parts = EntityViewOverride::checkInstanceId((string) $instance->id());

    if (!$parts) {
      return FALSE;
    }

    if ($parts['entity_type_id'] !== $this->getTargetEntityTypeId()) {
      return FALSE;
    }

    if ($parts['field_name'] !== $this->getDisplayBuilderOverrideField()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the available contexts for a given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of context objects for a given entity.
   */
  protected function getContextsForEntity(FieldableEntityInterface $entity): array {
    $available_context_ids = \array_keys($this->contextRepository()->getAvailableContexts());

    return [
      'view_mode' => new Context(ContextDefinition::create('string'), $this->getMode()),
      'entity' => EntityContext::fromEntity($entity),
      'display' => EntityContext::fromEntity($this),
    ] + $this->contextRepository()->getRuntimeContexts($available_context_ids);
  }

  /**
   * Wraps the context repository service.
   *
   * @return \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   *   The context repository service.
   */
  protected function contextRepository(): ContextRepositoryInterface {
    return \Drupal::service('context.repository');
  }

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  protected function getInstance(): ?InstanceInterface {
    $instance_id = $this->displayBuildable()->getInstanceId();
    /** @var \Drupal\display_builder\InstanceInterface|null $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($instance_id);
    $this->instance = $instance;

    return $this->instance;
  }

  /**
   * Gets the display buildable manager.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The manager for display buildable.
   */
  protected function displayBuildable(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('entity_view', ['entity' => $this]);

    return $buildable;
  }

  /**
   * Loads display builder by id.
   *
   * @param string $display_builder_id
   *   The display builder ID.
   *
   * @return \Drupal\display_builder\ProfileInterface|null
   *   The display builder, or NULL if not found.
   */
  private function loadDisplayBuilder(string $display_builder_id): ?ProfileInterface {
    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');

    /** @var \Drupal\display_builder\ProfileInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

  /**
   * Builds the render array for the sources of a given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param array $sources
   *   The sources to build.
   *
   * @return array
   *   The render array representing the sources of the entity.
   */
  private function buildSources(FieldableEntityInterface $entity, array $sources): array {
    $contexts = $this->getContextsForEntity($entity);
    $cacheability = new CacheableMetadata();
    $fake_build = [];

    foreach ($sources as $source_data) {
      $fake_build = $this->componentElementBuilder->buildSource($fake_build, 'content', [], $source_data, $contexts);
    }
    $build = $fake_build['#slots']['content'] ?? [];
    $build['#cache'] = $fake_build['#cache'] ?? [];
    // The render array is built based on decisions made by SourceStorage
    // plugins, and therefore it needs to depend on the accumulated
    // cacheability of those decisions.
    $cacheability->applyTo($build);

    return $build;
  }

}
