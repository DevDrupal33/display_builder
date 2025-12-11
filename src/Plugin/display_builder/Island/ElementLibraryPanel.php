<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\ComponentLibraryDefinitionHelper;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandConfigurationFormInterface;
use Drupal\display_builder\IslandConfigurationFormTrait;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Element library island plugin implementation.
 */
#[Island(
  id: 'element_library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Elements library'),
  description: new TranslatableMarkup('List of available elements.'),
  type: IslandType::Library,
)]
class ElementLibraryPanel extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  /**
   * The UI Patterns source plugin manager.
   */
  protected SourcePluginManager $sourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sourceManager = $container->get('plugin.manager.ui_patterns_source');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Elements';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'exclude' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();
    $elements = $this->getElements();
    $options = [];

    foreach ($elements as $component_id => $component) {
      $options[$component_id] = $component->metadata->name;
    }

    $form['exclude'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude elements'),
      '#options' => $options,
      '#default_value' => $configuration['exclude'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    if (empty($configuration['exclude'])) {
      return [];
    }

    $elements = $this->getElements();
    $excludes = \array_intersect_key($elements, \array_combine($configuration['exclude'], $configuration['exclude']));

    foreach ($excludes as $component_id => $exclude) {
      $excludes[$component_id] = $exclude->metadata->name;
    }

    return [
      $this->t('Excluded: @exclude', [
        '@exclude' => \implode(', ', $excludes),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    $configuration = $this->getConfiguration();
    $helper = new ComponentLibraryDefinitionHelper($this->sdcManager, $this->sourceManager);
    /** @var \Drupal\ui_patterns\SourceWithChoicesInterface $source */
    $source = $this->sourceManager->createInstance('component');
    $build = [];

    $elements = $this->getElements();

    foreach ($elements as $component_id => $component) {
      if (\in_array($component_id, $configuration['exclude'], TRUE)) {
        continue;
      }

      $vals = [
        'source_id' => 'component',
        'source' => $helper->prepareComponentData($source, $component),
      ];

      $build[] = $this->buildElementRenderable($component, $vals);
    }

    $build = $this->buildDraggables($builder_id, $build, 'mosaic');

    return [
      '#type' => 'component',
      '#component' => 'display_builder:library_panel',
      '#slots' => [
        'content' => $build,
      ],
    ];
  }

  /**
   * Build element renderable.
   *
   * @param \Drupal\Core\Plugin\Component $component
   *   Component.
   * @param array $vals
   *   Values used by HTMX.
   *
   * @return array
   *   Renderable array.
   */
  private function buildElementRenderable($component, array $vals): array {
    $build = $this->buildPlaceholderButton($component->metadata->name, $vals, NULL);
    // Label is used by default to set drawer title when dragging. It is set
    // on RenderableBuilderTrait::buildPlaceholderButton(), so here we need
    // to override it to have the proper label and not the variant name.
    // @see assets/js/db_drawer.js
    // @see src/RenderableBuilderTrait::buildPlaceholderButton()
    $build['#attributes']['data-node-title'] = $component->metadata->name;

    return $build;
  }

  /**
   * Get components which are elements.
   *
   * @return \Drupal\Core\Plugin\Component[]
   *   Elements.
   */
  private function getElements(): array {
    $elements = [];
    $components = $this->sdcManager->getDefinitions();

    foreach ($components as $component_id => $definition) {
      if ($definition['provider'] !== 'display_builder') {
        continue;
      }

      if (($definition['group'] ?? '') !== 'Generic') {
        continue;
      }

      $elements[$component_id] = $this->sdcManager->find($component_id);
    }
    \ksort($elements);

    return $elements;
  }

}
