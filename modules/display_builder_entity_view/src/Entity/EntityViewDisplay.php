<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Entity;

use Drupal\Core\Entity\Entity\EntityViewDisplay as CoreEntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Theme\Registry;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Provides an entity view display entity that has a display builder.
 *
 * When Layout Builder is not activated, extends the default entity view
 * display ("Manage display").
 *
 * @see Drupal\display_builder_entity_view\Hook\DisplayBuilderEntityViewHook::entityTypeAlter()
 * @see Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay
 */
class EntityViewDisplay extends CoreEntityViewDisplay implements DisplayBuilderEntityDisplayInterface, DisplayBuilderOverridableInterface {

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

  /**
   * Gets entity_view_display information grouped by entity type.
   *
   * @todo To remove.
   * https://www.drupal.org/project/display_builder/issues/3542273
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   An array of display information keyed with 'node', then 'modes' and
   *   'bundles':
   *
   *   @code
   *   [
   *     'node' => [
   *       'modes' => [
   *         'teaser' => 'Teaser',
   *       ],
   *      'bundles' => [
   *        'article' => [
   *          'teaser' => 'Teaser',
   *        ],
   *      ],
   *    ],
   *   ];
   *
   *   @endcode
   */
  public static function getDisplayInfos(EntityTypeManagerInterface $entityTypeManager): array {
    /** @var \Drupal\display_builder_entity_view\Entity\EntityViewDisplay[] $displays */
    $displays = $entityTypeManager
      ->getStorage('entity_view_display')
      ->loadMultiple();
    $view_mode_storage = $entityTypeManager->getStorage('entity_view_mode');
    $tabs_info = [];

    foreach ($displays as $display) {
      if (!$display->getDisplayBuilderOverrideField()) {
        continue;
      }

      $entity_type_id = $display->getTargetEntityTypeId();
      $view_mode = $view_mode_storage->load(\sprintf('%s.%s', $entity_type_id, $display->getMode()));
      $tabs_info[$entity_type_id]['modes'][$display->getMode()] = $view_mode?->label() ?? t('Default');
      $tabs_info[$entity_type_id]['bundles'][$display->getTargetBundle()][$display->getMode()] = $view_mode?->label() ?? t('Default');
    }

    return $tabs_info;
  }

}
