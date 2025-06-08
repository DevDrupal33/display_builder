<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Action\Attribute\ActionMethod;
use Drupal\Core\Entity\Entity\EntityViewDisplay as BaseEntityViewDisplay;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_entity_view\Controller\DisplayBuilderEntityViewController;

/**
 * Provides an entity view display entity that has a display builder.
 */
class DisplayBuilderEntityViewDisplay extends BaseEntityViewDisplay {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The source plugin manager.
   *
   * @var \Drupal\ui_patterns\SourcePluginManager
   */
  protected $sourcePluginManager;

  /**
   * The state manager service.
   *
   * @var \Drupal\display_builder\StateManager\StateManagerInterface
   */
  protected $stateManager;

  /**
   * The component element builder service.
   *
   * @var \Drupal\ui_patterns\Element\ComponentElementBuilder
   */
  protected $componentElementBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    // Set $entityFieldManager before calling the parent constructor because the
    // constructor will call init() which then calls setComponent() which needs
    // $entityFieldManager.
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    $this->sourcePluginManager = \Drupal::service('plugin.manager.ui_patterns_source');
    $this->stateManager = \Drupal::service('display_builder.state_manager');
    $this->componentElementBuilder = \Drupal::service('ui_patterns.component_element_builder');
    parent::__construct($values, $entity_type);
  }

  /**
   * Compatibility with layout_builder module.
   *
   * Sadly, we need to define this method
   * to be able to cohabit with layout_builder module.
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function isLayoutBuilderEnabled(): bool {
    return FALSE;
  }

  /**
   * Compatibility with layout_builder module.
   *
   * Sadly, we need to define this method
   * to be able to cohabit with layout_builder module.
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function isOverridable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities) {
    $build_list = parent::buildMultiple($entities);

    // If no display builder enable stop here.
    if (!$this->isDisplayBuilderEnabled()) {
      return $build_list;
    }

    // Display Builder can not be enabled for the '_custom' view mode that is
    // used for on-the-fly rendering of fields in isolation from the entity.
    if ($this->isCustomMode()) {
      return $build_list;
    }

    foreach ($entities as $id => $entity) {
      $build_list[$id]['_display_builder'] = $this->buildSources($entity);

      // Remove all fields with configurable display
      // from the existing build.
      foreach (array_keys($build_list[$id]) as $name) {
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
  public function calculateDependencies() {
    parent::calculateDependencies();
    $contexts = $this->stateManager->getContexts($this->getDisplayBuilderId());

    foreach ($this->getSources() as $source_data) {
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $source = $this->sourcePluginManager->getSource('', [], $source_data, $contexts);
      $this->addDependencies($source->calculateDependencies());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function createCopy($mode): static {
    // Disable Display Builder and remove any sources copied from the original.
    return parent::createCopy($mode)
      ->setSources([])
      ->disableDisplayBuilder();
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Disable Display Builder'), pluralize: FALSE)]
  public function disableDisplayBuilder(): static {
    $this->setThirdPartySetting('display_builder', 'enabled', FALSE);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Enable Display Builder'), pluralize: FALSE)]
  public function enableDisplayBuilder(): static {
    $this->setThirdPartySetting('display_builder', 'enabled', TRUE);

    return $this;
  }

  /**
   * Return sources.
   *
   * @return array
   *   The sources.
   */
  public function getSources(): array {
    return $this->getThirdPartySetting('display_builder', 'sources', []);
  }

  /**
   * {@inheritdoc}
   */
  public function isDisplayBuilderEnabled(): bool {
    // Display Builder must not be enabled for the '_custom' view mode that is
    // used for on-the-fly rendering of fields in isolation from the entity.
    if ($this->isCustomMode()) {
      return FALSE;
    }

    return (bool) $this->getThirdPartySetting('display_builder', 'enabled');
  }

  /**
   * {@inheritdoc}
   *
   * @todo Move this upstream in https://www.drupal.org/node/2939931.
   */
  public function label() {
    $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($this->getTargetEntityTypeId());
    $bundle_label = $bundle_info[$this->getTargetBundle()]['label'];
    $target_entity_type = $this->entityTypeManager()->getDefinition($this->getTargetEntityTypeId());

    return new TranslatableMarkup('@bundle @label', [
      '@bundle' => $bundle_label,
      '@label' => $target_entity_type->getPluralLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    // Loop through all sources and determine if the removed dependencies are
    // used by their layout plugins.
    $contexts = $this->stateManager->getContexts($this->getDisplayBuilderId());

    foreach ($this->getSources() as $delta => $source_data) {
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $source = $this->sourcePluginManager->getSource('', [], $source_data, $contexts);
      $source_dependencies = $source->calculateDependencies();
      $source_removed_dependencies = $this->getPluginRemovedDependencies($source_dependencies, $dependencies);

      if ($source_removed_dependencies) {
        // @todo Allow the plugins to react to their dependency removal in
        //   https://www.drupal.org/project/drupal/issues/2579743.
        $this->removeSource($delta);
        $changed = TRUE;
      }
    }

    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    $already_enabled = isset($this->original) ? $this->original->isDisplayBuilderEnabled() : FALSE;
    $set_enabled = $this->isDisplayBuilderEnabled();

    if ($already_enabled !== $set_enabled) {
      if ($set_enabled) {
        // Loop through all existing field-based components and add them as
        // pre-configured sources ? (like layout builder does).
      }
      else {
        // When being disabled, remove all existing source data.
        $this->removeAllSources();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllSources(): static {
    $this->setSources([]);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeSource(mixed $delta): static {
    $sources = $this->getSources();
    unset($sources[$delta]);
    $this->setSources($sources);

    return $this;
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
    // plugins and therefore it needs to depend on the accumulated
    // cacheability of those decisions.
    $cacheability->applyTo($build);

    return $build;
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
    $available_context_ids = \array_keys($this->contextRepository()->getAvailableContexts());

    return [
      'view_mode' => new Context(ContextDefinition::create('string'), $this->getMode()),
      'entity' => EntityContext::fromEntity($entity),
      'display' => EntityContext::fromEntity($this),
    ] + $this->contextRepository()->getRuntimeContexts($available_context_ids);
  }

  /**
   * Get the display builder ID.
   *
   * @return string
   *   The display builder ID.
   */
  protected function getDisplayBuilderId(): string {
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $view_mode_name = $this->getMode();

    return DisplayBuilderEntityViewController::getDisplayBuilderId($entity_type_id, $bundle, $view_mode_name);
  }

  /**
   * Indicates if this display is using the '_custom' view mode.
   *
   * @return bool
   *   TRUE if this display is using the '_custom' view mode, FALSE otherwise.
   */
  protected function isCustomMode() {
    return $this->getOriginalMode() === static::CUSTOM_MODE;
  }

  /**
   * {@inheritdoc}
   */
  protected function setSources(array $sources): static {
    // Third-party settings must be completely unset instead of stored as an
    // empty array.
    if (!$sources) {
      $this->unsetThirdPartySetting('display_builder', 'sources');
    }
    else {
      $this->setThirdPartySetting('display_builder', 'sources', \array_values($sources));
    }

    return $this;
  }

}
