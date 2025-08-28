<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Theme\Registry;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay as CoreLayoutBuilderEntityViewDisplay;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Provides an entity view display entity that has a display builder.
 *
 * When Layout Builder is activated, extends Layout Builder.
 *
 * @see Drupal\display_builder_entity_view\Hook\DisplayBuilderEntityViewHook::entityTypeAlter()
 * @see Drupal\display_builder_entity_view\Entity\EntityViewDisplay
 */
class LayoutBuilderEntityViewDisplay extends CoreLayoutBuilderEntityViewDisplay implements DisplayBuilderEntityDisplayInterface, DisplayBuilderOverridableInterface {

  use EntityViewDisplayTrait;

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
   * The theme registry.
   */
  protected Registry $themeRegistry;

  /**
   * The list of modules.
   */
  protected ModuleExtensionList $modules;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs the EntityViewDisplayTrait.
   *
   * @param array $values
   *   The values to initialize the entity with.
   * @param string $entity_type
   *   The entity type ID.
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
    $this->themeRegistry = \Drupal::service('theme.registry');
    $this->modules = \Drupal::service('extension.list.module');
  }

}
