<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\Entity\EntityViewDisplay as CoreEntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_entity_view\BuilderDataConverter;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Provides an entity view display entity that has a display builder.
 *
 * When Layout Builder is not activated, extends the default entity view
 * display ("Manage display").
 *
 * @see \Drupal\display_builder_entity_view\Hook\DisplayBuilderEntityViewHook::entityTypeAlter()
 * @see \Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay
 */
class EntityViewDisplay extends CoreEntityViewDisplay implements DisplayBuilderEntityDisplayInterface {

  use EntityViewDisplayTrait;

  /**
   * The source plugin manager.
   */
  protected SourcePluginManager $sourcePluginManager;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

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
   * Constructs the EntityViewDisplay.
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

}
