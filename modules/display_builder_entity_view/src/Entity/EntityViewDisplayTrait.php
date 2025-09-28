<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Symfony\Component\HttpFoundation\ParameterBag;

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

    if (!$this->getInstanceId()) {
      // If there is no instance ID, we cannot calculate dependencies.
      return $this;
    }

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($this->getInstanceId());

    if (!$instance) {
      return $this;
    }
    $contexts = $instance->getContexts();

    if (!$contexts) {
      return $this;
    }

    foreach ($this->getSources() as $source_data) {
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
   * @see \Drupal\display_builder_entity_view\DisplayBuilderEnabledInterface
   */
  public function isDisplayBuilderEnabled(): bool {
    // Display Builder must not be enabled for the '_custom' view mode that is
    // used for on-the-fly rendering of fields in isolation from the entity.
    if ($this->getOriginalMode() === static::CUSTOM_MODE) {
      return FALSE;
    }

    return (bool) $this->getProfile();
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

    foreach ($this->getSources() as $source_data) {
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
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    return 'entity_view__';
  }

  /**
   * Returns the context requirement for this entity view display.
   *
   * This is used to ensure that the entity context is available when building
   * the display builder.
   *
   * @return string
   *   The context requirement string.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public static function getContextRequirement(): string {
    return 'entity';
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, EntityViewDisplay::getPrefix())) {
      return NULL;
    }
    [, $entity, $bundle, $view_mode] = \explode('__', $instance_id);

    return [
      'entity' => $entity,
      'bundle' => $bundle,
      'view_mode' => $view_mode,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $fieldable_entity_type = $this->entityTypeManager->getDefinition($this->getTargetEntityTypeId());
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $parameters = [
      $bundle_parameter_key => $this->getTargetBundle(),
      'view_mode_name' => $this->getMode(),
    ];
    $route_name = \sprintf('display_builder_entity_view.%s', $this->getTargetEntityTypeId());

    return Url::fromRoute($route_name, $parameters);
  }

  /**
   * Create a display buildable from route.
   *
   * @param string $route
   *   The route name.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $params
   *   The parameters of the route we check.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   An implementation of the interface.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public static function createFromRoute(string $route, ParameterBag $params): ?DisplayBuildableInterface {
    $entity_type = $params->get('entity_type_id');
    $bundle = $params->get('bundle');
    $view_mode = $params->get('view_mode_name');

    if ($entity_type === NULL || $bundle === NULL || $view_mode === NULL) {
      return NULL;
    }

    if ($route !== 'display_builder_entity_view.' . $entity_type) {
      return NULL;
    }

    $display_id = "{$entity_type}.{$bundle}.{$view_mode}";
    $storage = \Drupal::service('entity_type.manager')->getStorage('entity_view_display');

    $entity_display = $storage->load($display_id);

    if (!($entity_display instanceof DisplayBuildableInterface)) {
      return NULL;
    }

    return $entity_display;
  }

  /**
   * Returns the URL for the display builder from an instance id.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return \Drupal\Core\Url
   *   The url of the instance.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $route_name = \sprintf('display_builder_entity_view.%s', $params['entity']);

    return Url::fromRoute($route_name, $params);
  }

  /**
   * Returns the URL for the display builder from an instance id.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return \Drupal\Core\Url
   *   The url of the instance.
   *
   * @see Drupal\display_builder\DisplayBuildableInterface
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $route_name = \sprintf('entity.entity_view_display.%s.view_mode', $params['entity']);

    return Url::fromRoute($route_name, $params);
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
    return $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY);
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
    $display_builder_id = $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY);

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
   * Returns the display builder instance.
   *
   * @return \Drupal\display_builder\ProfileInterface|null
   *   The display builder instance, or NULL if not set.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function getProfile(): ?ProfileInterface {
    $display_builder_id = $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY);

    if ($display_builder_id === NULL) {
      return NULL;
    }

    return $this->loadDisplayBuilder($display_builder_id);
  }

  /**
   * Returns the instance ID for the display builder.
   *
   * @return string|null
   *   The instance ID for the display builder, or NULL if the entity is new.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', EntityViewDisplay::getPrefix(), \str_replace('.', '__', $this->id));
  }

  /**
   * Checks access.
   *
   * @param string $instance_id
   *   Instance entity ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\display_builder\InstanceAccessControlHandler
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $permission = 'administer ' . $params['entity'] . ' display';

    return $account->hasPermission($permission) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Initializes the display builder instance if it is missing.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceStorage $storage */
    $storage = $this->entityTypeManager->getStorage('display_builder_instance');

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->load($this->getInstanceId());

    if (!$instance) {
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      // initialImport() has two implementations:
      // - EntityViewDisplay::initialImport()
      // - LayoutBuilderEntityViewDisplay::initialImport()
      $sources = $this->initialImport();
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $view_mode = $this->getMode();
    $sampleEntity = $this->sampleEntityGenerator->get($entity_type_id, $bundle);
    $contexts = [
      'entity' => EntityContext::fromEntity($sampleEntity),
      'bundle' => new Context(ContextDefinition::create('string'), $bundle),
      'view_mode' => new Context(ContextDefinition::create('string'), $view_mode),
    ];

    return RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
  }

  /**
   * Returns the sources of the display builder.
   *
   * @return array
   *   The sources of the display builder.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function getSources(): array {
    return $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, []);
  }

  /**
   * Saves the sources of the display builder.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function saveSources(): void {
    $data = $this->getInstance()->getCurrentState();
    $this->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, $data);
    $this->save();
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
    if ($profile = $this->getProfile()) {
      $this->initInstanceIfMissing();

      // Save the profile in the instance if changed.
      $instance = $this->getInstance();
      $profile_id = (string) $profile->id();

      if ($instance->getProfile()->id() !== $profile_id) {
        $instance->setProfile($profile_id);
        $instance->save();
      }
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
        \assert($overridden_field instanceof DisplayBuildableInterface);
        $sources = $overridden_field->getSources();
      }

      // If the overridden field is empty fallback to the entity view.
      if (\count($sources) === 0) {
        $sources = $this->getSources();
      }

      // @see entity.html.twig
      $build_list[$id]['content'] = $this->buildSources($entity, $sources);
    }

    return $build_list;
  }

  /**
   * Chef if the instance is overriding this display.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   A list of display builder instances.
   *
   * @return bool
   *   Is the instance overriding this display?
   */
  protected function isOverrideOfCurrentDisplay(InstanceInterface $instance): bool {
    $parts = DisplayBuilderItemList::checkInstanceId((string) $instance->id());

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
    /** @var \Drupal\display_builder\InstanceInterface|null $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($this->getInstanceId());
    $this->instance = $instance;

    return $this->instance;
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

  /**
   * Returns the URL for the display builder from an instance id.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return array
   *   The url parameters for this instance id.
   */
  private static function getUrlParamsFromInstanceId(string $instance_id): array {
    [, $entity, $bundle, $view_mode] = \explode('__', $instance_id);
    $fieldable_entity_type = \Drupal::service('entity_type.manager')->getDefinition($entity);
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';

    return [
      $bundle_parameter_key => $bundle,
      'view_mode_name' => $view_mode,
      'entity' => $entity,
    ];
  }

}
