<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Common methods for entity view display.
 */
trait EntityViewDisplayTrait {

  /**
   * The source plugin manager.
   */
  protected SourcePluginManager $sourcePluginManager;

  /**
   * The state manager service.
   */
  protected StateManagerInterface $stateManager;

  /**
   * The component element builder service.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    // Set $entityFieldManager before calling the parent constructor because the
    // constructor will call init() which then calls setComponent() which needs
    // $entityFieldManager.
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    parent::__construct($values, $entity_type);
    $this->sourcePluginManager = \Drupal::service('plugin.manager.ui_patterns_source');
    $this->stateManager = \Drupal::service('display_builder.state_manager');
    $this->componentElementBuilder = \Drupal::service('ui_patterns.component_element_builder');
    $this->sampleEntityGenerator = \Drupal::service('ui_patterns.sample_entity_generator');
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
  protected function displayBuilderBuildMultiple(array $entities, array $build_list): array {
    // If no display builder enabled stop here.
    if (!$this->isDisplayBuilderEnabled()) {
      return $build_list;
    }

    foreach ($entities as $id => $entity) {
      $build_list[$id]['_display_builder'] = $this->buildSources($entity);

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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);

    // Loop through all sources and determine if the removed dependencies are
    // used by their layout plugins.
    $display_builder_id = $this->getDisplayBuilder() ? (string) $this->getDisplayBuilder()->id() : '';
    $contexts = $this->stateManager->getContexts($display_builder_id);

    foreach ($this->getSources() as $source_data) {
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $source = $this->sourcePluginManager->getSource('', [], $source_data, $contexts);
      $source_dependencies = $source->calculateDependencies();
      $source_removed_dependencies = $this->getPluginRemovedDependencies($source_dependencies, $dependencies);

      if ($source_removed_dependencies) {
        // @todo Allow the plugins to react to their dependency removal in
        //   https://www.drupal.org/project/drupal/issues/2579743.
        // $this->removeSource($delta);
        $changed = TRUE;
      }
    }

    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    // @todo Change the context ID.
    // See https://www.drupal.org/project/display_builder/issues/3534579
    return 'display_builder_entity_view';
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

    return Url::fromRoute("display_builder_entity_view.{$this->getTargetEntityTypeId()}", $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $entity = \explode('__', $instance_id)[1];
    $bundle = \explode('__', $instance_id)[2];
    $view_mode = \explode('__', $instance_id)[3];
    $fieldable_entity_type = \Drupal::service('entity_type.manager')->getDefinition($entity);
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $params = [
      $bundle_parameter_key => $bundle,
      'view_mode_name' => $view_mode,
    ];

    return Url::fromRoute('display_builder_entity_view.' . $entity, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface {
    $display_builder_id = $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, '');

    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager()->getStorage('display_builder');

    /** @var \Drupal\display_builder\DisplayBuilderInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->isNew()) {
      return NULL;
    }

    return 'entity_view__' . \str_replace('.', '__', $this->id);
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, []);
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $data = $this->stateManager->getCurrentState($this->getInstanceId());
    $this->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, $data);
    $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    if ($this->getDisplayBuilder()) {
      $this->initInstanceIfMissing();
    }

    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    if ($this->getInstanceId()) {
      $this->stateManager->delete($this->getInstanceId());
    }
    parent::delete();
  }

  /**
   * Convert "Manage display" formatters to sources.
   *
   * @return array
   *   List of UI Patterns sources.
   */
  protected function convertManageDisplayData(): array {
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
  protected function convertSingleField(string $field_id, array $data): array {
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
  protected function initContexts(): array {
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
   *
   * @return array
   *   The render array representing the sources of the entity.
   */
  protected function buildSources(FieldableEntityInterface $entity) {
    $contexts = $this->getContextsForEntity($entity);
    $label = new TranslatableMarkup('@entity being viewed', [
      '@entity' => $entity->getEntityType()->getSingularLabel(),
    ]);
    $contexts['display_builder.entity'] = EntityContext::fromEntity($entity, (string) $label);

    $cacheability = new CacheableMetadata();
    $fake_build = [];

    foreach ($this->getSources() as $source_data) {
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
   * Gets the available contexts for a given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of context objects for a given entity.
   */
  protected function getContextsForEntity(FieldableEntityInterface $entity) {
    $available_context_ids = array_keys($this->contextRepository()->getAvailableContexts());
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
  protected function contextRepository() {
    return \Drupal::service('context.repository');
  }

}
