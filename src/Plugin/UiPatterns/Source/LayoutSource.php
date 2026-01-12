<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Drupal\ui_patterns\UiPatternsNormalizerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'layout',
  label: new TranslatableMarkup('Layout'),
  prop_types: ['slot']
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
      $container->get('plugin.manager.core.layout'),
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

    foreach ($this->settings['regions'] as $region_id => $region) {
      foreach ($region as $source) {
        $content = $this->componentElementBuilder->buildSource([], 'content', [], $source, $this->configuration['contexts'] ?? []) ?? [];
        $content = $content['#slots']['content'][0] ?? [];
        $regions[$region_id][] = $content;
      }
    }

    return $layout->build($regions);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $layout = $this->getLayout();

    if ($layout instanceof PluginFormInterface) {
      return $layout->buildConfigurationForm($form, $form_state);
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

    // @todo calculate dependencies.
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotDefinitions(): array {
    $slots = [];
    $layout_id = $this->configuration['layout_id'];
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
    return $this->settings['regions'] ?? [];
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
   * Get layout plugin.
   *
   * @return \Drupal\Core\Layout\LayoutInterface|null
   *   The layout plugin.
   */
  private function getLayout(): ?LayoutInterface {
    $layout_id = $this->settings['layout_id'] ?? NULL;

    if (!$layout_id) {
      return NULL;
    }

    return $this->layoutManager->createInstance($layout_id, $this->settings['settings']);
  }

}
