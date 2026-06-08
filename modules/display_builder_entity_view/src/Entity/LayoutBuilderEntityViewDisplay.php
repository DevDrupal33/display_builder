<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_entity_view\BuilderDataConverter;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay as CoreLayoutBuilderEntityViewDisplay;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Provides an entity view display entity that has a display builder.
 *
 * When Layout Builder is activated, extends Layout Builder.
 *
 * @see \Drupal\display_builder_entity_view\Hook\DisplayBuilderEntityViewHook::entityTypeAlter()
 * @see \Drupal\display_builder_entity_view\Entity\EntityViewDisplay
 */
class LayoutBuilderEntityViewDisplay extends CoreLayoutBuilderEntityViewDisplay implements DisplayBuilderEntityDisplayInterface {

  use EntityViewDisplayTrait;

  /**
   * The source plugin manager.
   */
  protected SourcePluginManager $sourcePluginManager;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The component element builder service.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The data converter from Manage Display and Layout Builder.
   */
  protected BuilderDataConverter $dataConverter;

  /**
   * The display buildable plugin manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * Constructs the LayoutBuilderEntityViewDisplay.
   *
   * @param array $values
   *   The values to initialize the entity with.
   * @param string $entity_type
   *   The entity type ID.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->sourcePluginManager = \Drupal::service('plugin.manager.ui_patterns_source');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->componentElementBuilder = \Drupal::service('ui_patterns.component_element_builder');
    $this->dataConverter = \Drupal::service('display_builder_entity_view.builder_data_converter');
    $this->displayBuildableManager = \Drupal::service('plugin.manager.display_buildable');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    // If the update is made from Layout Builder, convert the data and copy
    // to Display Builder's third party settings storage.
    if (isset($this->form_id) && $this->form_id === 'entity_view_display_layout_builder_form') {
      if ($this->displayBuildable()->getProfile()) {
        $this->importFromLayoutBuilder();
      }
    }
    parent::preSave($storage);
  }

  /**
   * Import and convert data from layout builder.
   *
   * This is not used for the first import but for the following saves.
   *
   * @see LayoutBuilderEntityViewDisplay::preSave()
   */
  protected function importFromLayoutBuilder(): void {
    if (!$this->displayBuildable()->getInstanceId()) {
      return;
    }
    $sections = $this->getThirdPartySetting('layout_builder', 'sections');
    $sources = $this->dataConverter->convertFromLayoutBuilder($sections);

    /** @var \Drupal\display_builder\InstanceStorage $storage */
    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->load($this->displayBuildable()->getInstanceId());
    $instance->setNewPresent($sources, 'Import from Layout Builder');
    $instance->save();
  }

}
