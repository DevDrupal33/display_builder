<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\Island\RealRenderTrait;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\display_builder\ThirdPartySettingsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Scaffold island plugin implementation.
 *
 * A hierarchical, preview-less "card per element" view built from the
 * display_builder:layer component - except for a configurable allowlist of
 * component IDs, rendered with their real output instead
 * (@see RealRenderTrait::buildComponentRealRender()), so the actual grid/layout
 * nesting is visible at a glance.
 *
 * Which components count as "layout" is theme-specific, hence a configurable
 * list rather than a hardcoded one.
 */
#[Island(
  id: 'scaffold',
  label: new TranslatableMarkup('Scaffold'),
  description: new TranslatableMarkup('Schematic hierarchical view, with configured components (e.g. grid rows) rendered with their real output.'),
  type: IslandType::View,
  region: 'main',
  icon: 'grid-3x3-gap',
)]
class ScaffoldPanel extends ViewPanelBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;
  use RealRenderTrait;

  /**
   * Island plugins manager.
   */
  protected IslandPluginManagerInterface $islandManager;

  /**
   * Configured component IDs to render for real, as a set. Keys are the IDs.
   *
   * @var array<string, int>|null
   */
  private ?array $layoutComponentIds = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->islandManager = $container->get('plugin.manager.db_island');
    // The only panel besides the Canvas that renders anything for real, and
    // only for its configured allowlist. @see ::renderComponent().
    $instance->initRealRender($container);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'g',
      'help' => t('Show the scaffold'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    parent::setConfiguration($configuration);
    // Drop the memo: the form submit handler reconfigures a live instance.
    $this->layoutComponentIds = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'components' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['components'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Render components'),
      '#description' => $this->t('Component IDs to render with their real output instead of a placeholder. One per line. Example: "ui_suite_bootstrap:grid_row_1". Recommended usage is to set components used as layout/grid.'),
      '#default_value' => $configuration['components'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $count = \count($this->getLayoutComponentIds());

    return [
      $this->formatPlural($count, '@count component rendered with real output', '@count components rendered with real output'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $build = $this->buildRootDropzone($builder, $data);

    if (empty($build['#slots']['content'])) {
      // Load an empty component to have any assets with it.
      $build['#slots']['content'] = [
        '#type' => 'component',
        '#component' => 'display_builder:layer',
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function renderComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, string $component_id, string $label, int $index): ?array {
    if (isset($this->getLayoutComponentIds()[$component_id])) {
      return $this->buildComponentRealRender($instance, $node_id, $source, $data, $component_id, $label, $index);
    }

    return $this->renderSchematicComponent($instance, $node_id, $source, $data, $label, $index);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(InstanceInterface $instance, string $node_id, array $data, int $index = 0): array {
    $label = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);

    if (isset($data['source_id']) && $data['source_id'] === 'entity_field') {
      $label['summary'] = (string) $this->t('Field: @label', ['@label' => $label['label']]);
    }

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:layer',
      '#slots' => [
        'title' => $label['summary'],
      ],
    ];

    $node_id = $node_id ?: $data['node_id'] ?? NULL;

    if (!$node_id) {
      $this->logger->error('[ScaffoldPanel::buildSingleBlock] missing instance ID. <pre>' . \print_r($data, TRUE) . '</pre>');

      return $build;
    }

    $build = $this->addThirdPartySettingsSummary($data, $build);

    $build['#attributes'] = $this->buildNodeAttributes($label['summary'], $index, $data['source_id'] ?? NULL);
    $build['#attributes']['data-testid'] = $label['label'] ?? '_' . $index;

    return $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $label['summary'], $index);
  }

  /**
   * Build a component as a schematic layer card, without its real output.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string $node_id
   *   The node ID of the component.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $data
   *   The node data.
   * @param string $label
   *   The component label.
   * @param int $index
   *   The index of the node among its siblings.
   *
   * @return array
   *   The layer component renderable array.
   */
  private function renderSchematicComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, string $label, int $index): array {
    $slots = [];

    foreach ($source->getSlotDefinitions() as $slot_id => $definition) {
      $slots[] = [
        [
          '#plain_text' => $definition['title'],
        ],
        $this->buildComponentSlot($instance, $source, $slot_id, $definition, $node_id, $label),
      ];
    }

    // Callers always come through ::digFromSlot() or ::replaceNode(), both of
    // which require a 'source_id', so the chain always resolves.
    $testid_source = $data['source']['component']['component_id']
      ?? $data['source']['plugin_id']
      ?? $data['source']['derivable_context']
      ?? $data['source_id'];

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:layer',
      '#slots' => [
        'title' => $label,
        'children' => $slots,
      ],
      '#attributes' => \array_merge(
        $this->buildNodeAttributes($label, $index),
        ['data-testid' => 'layer_' . $testid_source],
      ),
    ];

    $build = $this->addThirdPartySettingsSummary($data, $build);
    $build = $this->addComponentSettingsSummary($source, $build);

    return $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $source->label(), $index);
  }

  /**
   * Add third party settings summary to layer's info slot.
   *
   * @param array $data
   *   The node data.
   * @param array $build
   *   The layer component renderable array.
   *
   * @return array
   *   The layer component renderable array.
   */
  private function addThirdPartySettingsSummary(array $data, array $build): array {
    if (!isset($data['third_party_settings'])) {
      return $build;
    }

    foreach ($data['third_party_settings'] as $provider => $settings) {
      // In Display Builder, third_party_settings providers can be:
      // - an island plugin ID (our 'normal' way)
      // - a Drupal module name (the Drupal way, found in displays imported and
      // converted, not leveraged by us for now but we may do it later).
      // So, let's check the plugin ID exists before running logic.
      if (!$this->islandManager->hasDefinition($provider)) {
        continue;
      }
      $island = $this->islandManager->createInstance($provider, $settings);

      if ($island instanceof ThirdPartySettingsInterface && $summary = $island->getSummary()) {
        $build['#slots']['info'] = \array_merge($build['#slots']['info'] ?? [], $summary);
      }
    }

    return $build;
  }

  /**
   * Add config settings summary to layer's info slot.
   *
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $build
   *   The layer component renderable array.
   *
   * @return array
   *   The layer component renderable array.
   */
  private function addComponentSettingsSummary(SourceWithSlotsInterface $source, array $build): array {
    $items = [];

    foreach ($source->settingsSummary() as $item) {
      if ($item !== NULL) {
        $items[] = [
          '#type' => 'html_tag',
          '#tag' => 'li',
          '#value' => $item,
        ];
      }
    }

    if (empty($items)) {
      return $build;
    }

    $summary = [
      [
        '#type' => 'html_tag',
        '#tag' => 'em',
        '#value' => new TranslatableMarkup('Config'),
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'ul',
        '#attributes' => [
          'class' => ['summary'],
        ],
        0 => $items,
      ],
    ];

    $build['#slots']['info'] = \array_merge($build['#slots']['info'] ?? [], $summary);

    return $build;
  }

  /**
   * The configured component IDs to render with their real output.
   *
   * Resolved once: the configuration cannot change during a render, and this is
   * asked on each of the O(nodes) calls to ::renderComponent().
   *
   * @return array<string, int>
   *   Component IDs as keys, e.g. "ui_suite_bootstrap:grid_row_1".
   */
  private function getLayoutComponentIds(): array {
    if ($this->layoutComponentIds === NULL) {
      $configuration = $this->getConfiguration();
      $ids = \array_filter(\preg_split('/\r\n|\r|\n/', \trim($configuration['components'] ?? '')) ?: []);
      $this->layoutComponentIds = \array_flip($ids);
    }

    return $this->layoutComponentIds;
  }

}
