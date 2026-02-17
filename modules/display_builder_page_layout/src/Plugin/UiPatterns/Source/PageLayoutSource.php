<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\UiPatterns\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\PropTypePluginManager;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\UiPatternsNormalizerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'page_layout',
  label: new TranslatableMarkup('Page layout (from active theme)'),
  prop_types: ['slot'],
  context_requirements: ['page'],
  context_definitions: []
)]
class PageLayoutSource extends SourcePluginBase implements SourceWithSlotsInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PropTypePluginManager $propTypeManager,
    ContextRepositoryInterface $contextRepository,
    RouteMatchInterface $routeMatch,
    SampleEntityGeneratorInterface $sampleEntityGenerator,
    ModuleHandlerInterface $moduleHandler,
    Token $token,
    UiPatternsNormalizerInterface $normalizer,
    private ComponentElementBuilder $componentElementBuilder,
    private SourcePluginManager $sourceManager,
    private ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $propTypeManager, $contextRepository, $routeMatch, $sampleEntityGenerator, $moduleHandler, $token, $normalizer);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ui_patterns_prop_type'),
      $container->get('context.repository'),
      $container->get('current_route_match'),
      $container->get('ui_patterns.sample_entity_generator'),
      $container->get('module_handler'),
      $container->get('token'),
      $container->get('ui_patterns.normalizer'),
      $container->get('ui_patterns.component_element_builder'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('config.factory'),
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $page = [
      '#type' => 'page',
    ];

    foreach ($this->getSlotValues() as $region_id => $region) {
      $page[$region_id] = [];

      foreach ($region as $source) {
        $content = $this->componentElementBuilder->buildSource([], 'content', [], $source, $this->configuration['contexts'] ?? []) ?? [];
        $content = $content['#slots']['content'][0] ?? [];
        $page[$region_id][] = $content;
      }
    }

    return $page;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

    foreach ($this->getSlotValues() as $slot_id => $slot) {
      // A slot is a list of source data.
      $slot = \array_is_list($slot) ? $slot : [$slot];

      foreach ($slot as $source) {
        if ($source = $this->sourceManager->getSource($slot_id, $slot_definition, $source, [])) {
          SourcePluginBase::mergeConfigDependencies(
            $dependencies,
            $source->calculateDependencies()
          );
        }
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotDefinitions(): array {
    $slots = [];
    $theme_name = $this->configFactory->get('system.theme')->get('default');
    $regions = $this->systemRegionList($theme_name);

    foreach ($regions as $region => $title) {
      $slots[$region]['title'] = (string) $title;
    }

    return $slots;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValues(): array {
    return $this->configuration['settings']['regions'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValue(string $slot_id): array {
    return $this->configuration['settings']['regions'][$slot_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setSlotValue(array $data, string $slot_id, array $slot): array {
    $data['regions'][$slot_id] = $slot;

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setSlotRenderable(array $build, string $slot_id, array $slot): array {
    $build[$slot_id] = $slot;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSlotPath(string $slot_id): array {
    return ['regions', $slot_id];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormPropsOnly(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * Wraps system_region_list().
   */
  protected function systemRegionList(string $theme): array {
    return system_region_list($theme, REGIONS_VISIBLE);
  }

}
