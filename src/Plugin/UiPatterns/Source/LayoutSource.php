<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\PluginFormInterface;
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
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Drupal\ui_patterns\UiPatternsNormalizerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'layout',
  label: new TranslatableMarkup('Layout'),
  prop_types: ['slot'],
  context_requirements: ['layout_discovery'],
)]
class LayoutSource extends SourcePluginBase implements SourceWithChoicesInterface, SourceWithSlotsInterface {

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
    protected LayoutPluginManagerInterface $layoutManager,
    protected SourcePluginManager $sourceManager,
    protected ThemeHandlerInterface $themeHandler,
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
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('theme_handler'),
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    $layout = $this->getLayout();

    return [
      'layout_id' => NULL,
      'settings' => $layout->defaultConfiguration() ?? [],
      'regions' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $layout = $this->getLayout();

    if (!$layout) {
      return [];
    }

    $regions = [];

    foreach ($this->configuration['settings']['regions'] ?? [] as $region_id => $region) {
      foreach ($region as $source) {
        $content = $this->componentElementBuilder->buildSource([], 'content', [], $source, $this->configuration['contexts'] ?? []) ?? [];
        $content = $content['#slots']['content'][0] ?? [];

        // An empty render array is enough to cancel the rendering of the full
        // layout plugins, so let's remove them from the renderable.
        if (empty($content)) {
          continue;
        }
        $regions[$region_id][] = $content;
      }
    }

    return $layout->build($regions);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    // Careful with the configuration/settings/settings hierarchy where:
    // - $this->configuration has everything UI Patterns needs to work properly
    //   (prop_id, prop_definition, contexts..)
    // - $this->configuration['settings'] is the source data from the source
    //   tree
    // - $this->configuration['settings']['settings'] is the layout plugin
    //   configuration.
    $form = parent::settingsForm($form, $form_state);
    $form['#tree'] = TRUE;

    // We are not implementing a slot sources form for $forms['regions'],
    // because Display Builder doesn't need layout regions to be available in
    // the component form.
    // @todo However, for compatibility with UI Patterns ecosystem, it would be
    // better to implement it anyway. It will be removed by
    // ::settingsFormPropsOnly() anyway.
    $form['regions'] = [];
    $layout = $this->getLayout();

    if ($layout instanceof PluginFormInterface) {
      $form['settings'] = $layout->buildConfigurationForm($form, $form_state);
      // Hide administrative label textfield.
      $form['settings']['label']['#type'] = 'hidden';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    // @todo settings summary
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChoices(): array {
    $definitions = $this->layoutManager->getGroupedDefinitions();
    $choices = [];

    foreach ($definitions as $group_id => $group) {
      foreach ($group as $layout_id => $definition) {
        /** @var \Drupal\Core\Layout\LayoutDefinition $definition */
        $choice = [
          'label' => $definition->getLabel(),
          'original_id' => $layout_id,
          'group' => $group_id,
          'provider' => $definition,
        ];

        if ($choice['label'] instanceof MarkupInterface) {
          $choice['label'] = (string) $choice['label'];
        }
        $choices[$layout_id] = $choice;
      }
    }

    return $choices;
  }

  /**
   * {@inheritdoc}
   */
  public function getChoiceSettings(string $choice_id): array {
    return [
      'layout_id' => $choice_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getChoice(array $settings): string {
    return $settings['layout_id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $layout = $this->getLayout();
    $provider = [];

    // 1. Dependency of plugin.manager.core.layout.
    $provider['module'][] = 'layout_discovery';

    // 2. Layout provider extension (module or theme)
    $definition = $layout->getPluginDefinition();
    $provider = ($definition instanceof PluginDefinitionInterface) ? $definition->getProvider() : (string) ($definition['provider'] ?? '');
    $extension_type = $this->getExtensionType($provider);
    $dependencies[$extension_type][] = $provider;

    // 3. Layout plugin dependencies.
    SourcePluginBase::mergeConfigDependencies(
      $dependencies,
      $layout->calculateDependencies()
    );

    // 4. Sources in slots.
    $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

    foreach ($this->getSlotValues() as $slot_id => $slot) {
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
    $layout_id = $this->configuration['settings']['layout_id'] ?? NULL;

    if (!$layout_id) {
      return [];
    }
    $definition = $this->layoutManager->getDefinition($layout_id);

    foreach ($definition->getRegions() as $region_id => $region) {
      $slots[$region_id]['title'] = (string) $region['label'];
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
  public function getSlotCardinality(string $slot_id): int {
    // This source doesn't manage slot cardinality.
    return self::CARDINALITY_UNLIMITED;
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
    $form = $this->settingsForm($form, $form_state);
    unset($form['regions']);
    $layout = $this->getLayout();
    $form['layout_id'] = [
      '#type' => 'hidden',
      '#value' => $layout->getPluginId(),
    ];

    return $form;
  }

  /**
   * Get layout plugin.
   *
   * @return \Drupal\Core\Layout\LayoutInterface|null
   *   The layout plugin.
   */
  private function getLayout(): ?LayoutInterface {
    $layout_id = $this->configuration['settings']['layout_id'] ?? NULL;

    if (!$layout_id) {
      return NULL;
    }

    return $this->layoutManager->createInstance($layout_id, $this->configuration['settings']['settings'] ?? []);
  }

  /**
   * Get extension type (theme or module).
   *
   * @param string $extension
   *   Extension (module or theme) machine name.
   *
   * @return string
   *   Extension type.
   */
  private function getExtensionType(string $extension): string {
    if ($this->moduleHandler->moduleExists($extension)) {
      return 'module';
    }

    if ($this->themeHandler->themeExists($extension)) {
      return 'theme';
    }

    return '';
  }

}
