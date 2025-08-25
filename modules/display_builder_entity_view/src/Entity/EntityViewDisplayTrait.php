<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Common methods for entity view display.
 */
trait EntityViewDisplayTrait {

  /**
   * Calculates dependencies for the display builder.
   *
   * @return $this
   *   The current instance.
   */
  public function calculateDependencies(): self {
    parent::calculateDependencies();

    if (!$this->getInstanceId()) {
      // If there is no instance ID, we cannot calculate dependencies.
      return $this;
    }

    $contexts = $this->stateManager->getContexts($this->getInstanceId());

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
   */
  public function isDisplayBuilderEnabled(): bool {
    // Display Builder must not be enabled for the '_custom' view mode that is
    // used for on-the-fly rendering of fields in isolation from the entity.
    if ($this->getOriginalMode() === static::CUSTOM_MODE) {
      return FALSE;
    }

    return (bool) $this->getDisplayBuilder();
  }

  /**
   * Handler for when dependencies are removed.
   *
   * @param array $dependencies
   *   The dependencies that were removed.
   *
   * @return bool
   *   TRUE if the display can be overridden, FALSE otherwise.
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);

    // Loop through all sources and determine if the removed dependencies are
    // used by their plugins.
    $display_builder_id = $this->getDisplayBuilder() ? (string) $this->getDisplayBuilder()->id() : '';
    $contexts = $this->stateManager->getContexts($display_builder_id) ?? [];

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
   * Returns the context requirement for this entity view display.
   *
   * This is used to ensure that the entity context is available when building
   * the display builder.
   *
   * @return string
   *   The context requirement string.
   */
  public static function getContextRequirement(): string {
    return 'entity';
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, 'entity_view__')) {
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
    $fieldable_entity_type = $this->entityTypeManager()->getDefinition($this->getTargetEntityTypeId());
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $parameters = [
      $bundle_parameter_key => $this->getTargetBundle(),
      'view_mode_name' => $this->getMode(),
    ];
    $route_name = \sprintf('display_builder_entity_view.%s', $this->getTargetEntityTypeId());

    return Url::fromRoute($route_name, $parameters);
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
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    [, $entity, $bundle, $view_mode] = \explode('__', $instance_id);
    $fieldable_entity_type = \Drupal::service('entity_type.manager')->getDefinition($entity);
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $params = [
      $bundle_parameter_key => $bundle,
      'view_mode_name' => $view_mode,
    ];
    $route_name = \sprintf('display_builder_entity_view.%s', $entity);

    return Url::fromRoute($route_name, $params);
  }

  /**
   * Returns the field name used to store overridden displays.
   *
   * @return string|null
   *   The field name used to store overridden displays, or NULL if not set.
   */
  public function getDisplayBuilderOverrideField(): ?string {
    return $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY);
  }

  /**
   * Returns the display builder override profile.
   *
   * @return \Drupal\display_builder\DisplayBuilderInterface|null
   *   The display builder override profile, or NULL if not set.
   */
  public function getDisplayBuilderOverrideProfile(): ?DisplayBuilderInterface {
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
   */
  public function isDisplayBuilderOverridable(): bool {
    return !empty($this->getDisplayBuilderOverrideField())
      && $this->getDisplayBuilderOverrideProfile() !== NULL;
  }

  /**
   * Returns the display builder instance.
   *
   * @return \Drupal\display_builder\DisplayBuilderInterface|null
   *   The display builder instance, or NULL if not set.
   *
   * @see \Drupal\display_builder\WithDisplayBuilderInterface
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface {
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
   * @see \Drupal\display_builder\StateManagerInterface::getInstanceId()
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->isNew()) {
      return NULL;
    }

    return 'entity_view__' . \str_replace('.', '__', $this->id);
  }

  /**
   * Initializes the display builder instance if it is missing.
   */
  public function initInstanceIfMissing(): void {
    $instance_id = $this->getInstanceId();
    // One instance in State API by entity view display entity.
    $instance = $this->stateManager->load($instance_id);

    if ($instance !== NULL) {
      // The instance already exists in State Manager, so nothing to do.
      return;
    }
    // Init instance if missing in State Manager because new or deleted in the
    // State API.
    $contexts = $this->initContexts();
    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      $sources = $this->convertManageDisplayData();
    }

    $this->stateManager->create($instance_id, (string) $this->getDisplayBuilder()->id(), $sources, $contexts);
  }

  /**
   * Returns the sources of the display builder.
   *
   * @return array
   *   The sources of the display builder.
   */
  public function getSources(): array {
    return $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, []);
  }

  /**
   * Saves the sources of the display builder.
   */
  public function saveSources(): void {
    $data = $this->stateManager->getCurrentState($this->getInstanceId());
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
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    if ($this->getDisplayBuilder()) {
      $this->initInstanceIfMissing();
    }

    parent::postSave($storage, $update);
  }

  /**
   * Deletes the display builder instance if it exists.
   */
  public function delete(): void {
    if ($this->getInstanceId()) {
      $this->stateManager->delete($this->getInstanceId());
    }
    parent::delete();
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
   * Loads display builder by id.
   *
   * @param string $display_builder_id
   *   The display builder ID.
   *
   * @return \Drupal\display_builder\DisplayBuilderInterface|null
   *   The display builder, or NULL if not found.
   */
  private function loadDisplayBuilder(string $display_builder_id): ?DisplayBuilderInterface {
    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager()->getStorage('display_builder');

    /** @var \Drupal\display_builder\DisplayBuilderInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

  /**
   * Actual BuildMultiple.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface[] $entities
   *   The entities being displayed.
   * @param array $build_list
   *   Intermediary renderable array for the entities.
   *
   * @return array
   *   A renderable array for the entities, indexed by the same keys as the
   *   $entities array parameter.
   */
  private function displayBuilderBuildMultiple(array $entities, array $build_list): array {
    // If no display builder enabled stop here.
    if (!$this->isDisplayBuilderEnabled()) {
      return $build_list;
    }

    foreach ($entities as $id => $entity) {
      $sources = [];

      if ($this->isDisplayBuilderOverridable()) {
        $display_builder_field = $this->getDisplayBuilderOverrideField();
        $overridden_field = $entity->get($display_builder_field);
        \assert($overridden_field instanceof WithDisplayBuilderInterface);
        $sources = $overridden_field->getSources();
      }

      // If the overridden field doesn't provide sources fallback to
      // the entity view. Maybe we should handle this different.
      if (\count($sources) === 0) {
        $sources = $this->getSources();
      }
      $build_list[$id]['_display_builder'] = $this->buildSources($entity, $sources);

      // Remove all fields with configurable display
      // from the existing build.
      foreach (\array_keys($build_list[$id]) as $name) {
        $field_definition = $this->getFieldDefinition($name);

        if ($field_definition && $field_definition->isDisplayConfigurable($this->displayContext)) {
          unset($build_list[$id][$name]);
        }
      }
    }

    return $build_list;
  }

  /**
   * Convert "Manage display" formatters to sources.
   *
   * @return array
   *   List of UI Patterns sources.
   */
  private function convertManageDisplayData(): array {
    $definitions = $this->entityFieldManager->getFieldDefinitions($this->getTargetEntityTypeId(), $this->getTargetBundle());
    $sources = [];
    $fields = $this->content;
    \uasort($fields, [SortArray::class, 'sortByWeightElement']);

    foreach ($fields as $field_id => $field) {
      if (!isset($field['type'])) {
        // Probably an extra field. We don't support them.
        continue;
      }

      if (!$definitions[$field_id]->isDisplayConfigurable('view')) {
        // Hidden from Manage Display.
        continue;
      }
      $sources[] = $this->convertSingleField($field_id, $field);
    }

    return $sources;
  }

  /**
   * Convert field formatter plugin data to a source.
   *
   * @param string $field_id
   *   Field ID.
   * @param array $data
   *   Field formatter data.
   *
   * @return array
   *   A single UI Patterns source.
   */
  private function convertSingleField(string $field_id, array $data): array {
    $derivable_context = \implode(':', [
      $this->getTargetEntityTypeId(),
      $this->getTargetBundle(),
      $field_id,
    ]);
    $source = [
      'source_id' => 'field_formatter:' . $derivable_context,
      'source' => $data,
    ];

    return [
      'source_id' => 'entity_field',
      'source' => [
        'derivable_context' => 'field:' . $derivable_context,
        'field:' . $derivable_context => [
          'value' => [
            'sources' => [$source],
          ],
        ],
      ],
    ];
  }

  /**
   * Init contexts for entity view displays.
   *
   * @return array
   *   List of contexts.
   */
  private function initContexts(): array {
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
    $label = new TranslatableMarkup('@entity being viewed', [
      '@entity' => $entity->getEntityType()->getSingularLabel(),
    ]);
    $contexts['display_builder.entity'] = EntityContext::fromEntity($entity, (string) $label);

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
