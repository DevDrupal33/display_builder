<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\UiPatterns\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Registry;
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
  label: new TranslatableMarkup('Theme page (from active theme)'),
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
    protected ComponentElementBuilder $componentElementBuilder,
    protected SourcePluginManager $sourceManager,
    protected ConfigFactoryInterface $configFactory,
    protected Registry $themeRegistry,
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
  ): static {
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
      $container->get('theme.registry'),
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    // Page template may have been altered in runtime by PageLayoutPageVariant
    // or FullPageBuilderPageVariant. So, let's use the original template from
    // the permanent registry by adding it as a new runtime entry.
    $theme_registry = $this->themeRegistry->get();
    $runtime = $this->themeRegistry->getRuntime();
    $runtime->set('page_legacy', $theme_registry['page']);

    /** @var array<string, mixed> $page */
    $page = [
      '#theme' => 'page_legacy',
    ];

    foreach ($this->getSlotValues() as $region_id => $region) {
      $region_content = [];

      foreach ($region as $source) {
        $content = $this->componentElementBuilder->buildSource([], 'content', [], $source, $this->configuration['contexts'] ?? []) ?? [];
        $content = $content['#slots']['content'][0] ?? [];
        $region_content[] = $content;
      }

      // A single empty block is enough for Element::isRenderArray() to stop
      // considering the page render element as a render array.
      $region_content = \array_filter($region_content);
      $page[(string) $region_id] = $region_content;
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
  public function setSlotValue(string $slot_id, array $slot): array {
    $this->configuration['settings']['regions'][$slot_id] = $slot;

    return $this->configuration['settings'];
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
   * Lists a theme's visible regions.
   *
   * @param string $theme
   *   The machine name of the theme.
   *
   * @return array
   *   An array of region names and their human readable labels.
   */
  private function systemRegionList(string $theme): array {
    return \Drupal::service('theme_handler')->getTheme($theme)->listVisibleRegions(); // @phpcs:ignore
  }

}
