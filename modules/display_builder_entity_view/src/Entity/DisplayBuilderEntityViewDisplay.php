<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Action\Attribute\ActionMethod;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\StorageProperties;
use Drupal\display_builder_entity_view\Controller\DisplayBuilderEntityViewController;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Provides an entity view display entity that has a display builder.
 */
class DisplayBuilderEntityViewDisplay extends LayoutBuilderEntityViewDisplay {

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
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->sourcePluginManager = \Drupal::service('plugin.manager.ui_patterns_source');
    $this->stateManager = \Drupal::service('display_builder.state_manager');
    $this->componentElementBuilder = \Drupal::service('ui_patterns.component_element_builder');
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities): array {
    $build_list = parent::buildMultiple($entities);

    // If using Layout Builder stop here.
    if ($this->isLayoutBuilderEnabled()) {
      return $build_list;
    }

    // If no display builder enabled stop here.
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
    $this->unsetThirdPartySetting('display_builder', 'enabled');

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

    return (bool) $this->getThirdPartySetting('display_builder', 'enabled', FALSE);
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
      if (!$set_enabled) {
        // When being disabled, remove all existing source data.
        $this->removeAllSources();
        $this->unsetThirdPartySetting('display_builder', StorageProperties::ConfigEntityId->value);
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
    // plugins, and therefore it needs to depend on the accumulated
    // cacheability of those decisions.
    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Get the display builder ID.
   *
   * @return string
   *   The display builder ID.
   */
  protected function getDisplayBuilderId(): string {
    return DisplayBuilderEntityViewController::getDisplayBuilderId(
      $this->getTargetEntityTypeId(),
      $this->getTargetBundle(),
      $this->getMode()
    );
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
