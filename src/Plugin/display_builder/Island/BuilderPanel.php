<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Masterminds\HTML5;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builder island plugin implementation.
 */
#[Island(
  id: 'builder',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Builder'),
  description: new TranslatableMarkup('The Display Builder main island. Build the display with dynamic preview.'),
  type: IslandType::View,
  default_region: 'main',
  icon: 'tools',
)]
class BuilderPanel extends IslandPluginBase {

  use IslandReloadEventsTrait;

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * Proxy for slot source operations.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * The component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('renderer');
    $instance->slotSourceProxy = $container->get('display_builder.slot_sources_proxy');
    $instance->componentElementBuilder = $container->get('ui_patterns.component_element_builder');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'b',
      'help' => t('Show the builder'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:dropzone',
      '#props' => [
        'variant' => 'root',
      ],
      '#slots' => [
        'content' => $this->digFromSlot($builder_id, $data),
      ],
      '#attributes' => [
        // Required for JavaScript @see components/dropzone/dropzone.js.
        'data-db-id' => $builder_id,
        'data-node-title' => $this->t('Base container'),
        'data-db-root' => TRUE,
      ],
    ];

    return $this->htmxEvents->onRootDrop($build, $builder_id, $this->getPluginID());
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array {
    return $this->replaceNode($instance, $parent_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array {
    return $this->replaceNode($instance, $node_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array {
    if (empty($parent_id)) {
      return $this->reloadWithGlobalData($instance);
    }

    return $this->replaceNode($instance, $parent_id);
  }

  /**
   * Build renderable from state data.
   *
   * @param string $builder_id
   *   Display Builder ID.
   * @param string $instance_id
   *   The instance ID.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $data
   *   The UI Patterns form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  protected function buildSingleComponent(string $builder_id, string $instance_id, SourceWithSlotsInterface $source, array $data, int $index = 0): ?array {
    $info = $this->resolveComponentInfo($source, $data, $instance_id);

    if ($info === NULL) {
      return NULL;
    }

    ['component_id' => $component_id, 'label' => $label, 'instance_id' => $instance_id] = $info;

    $build = $this->renderSource($data);
    // Required for the context menu label.
    // @see assets/js/contextual_menu.js
    $build['#attributes']['data-node-title'] = $label;
    $build['#attributes']['data-slot-position'] = $index;
    $build['#attributes']['data-instance-id'] = $instance_id;

    foreach ($source->getSlotDefinitions() as $slot_id => $definition) {
      $slot = $this->buildComponentSlot($builder_id, $source, $slot_id, $definition, $instance_id);
      $build = $source->setSlotRenderable($build, $slot_id, $slot);
    }

    if ($this->isEmpty($build)) {
      // Keep the placeholder if the component is not renderable.
      $message = $component_id . ': ' . $this->t('Empty by default. Configure it to make it visible');
      $build = $this->buildPlaceholder($message);
    }

    if (!$this->useAttributesVariable($build)) {
      $build = $this->wrapContent($build);
    }

    return $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $source->label(), $index);
  }

  /**
   * Resolves component ID, label, and instance ID from source and data.
   *
   * Extracts the shared preamble logic used by all buildSingleComponent()
   * implementations across BuilderPanel, LayersPanel, and TreePanel.
   *
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $data
   *   The UI Patterns form state data.
   * @param string $instance_id
   *   The instance ID. May be empty; resolved from data['node_id'] as fallback.
   *
   * @return array{component_id: string, label: string, instance_id: string}|null
   *   Associative array with 'component_id', 'label', 'instance_id', or NULL
   *   if either component_id or instance_id could not be resolved.
   */
  protected function resolveComponentInfo(SourceWithSlotsInterface $source, array $data, string $instance_id): ?array {
    $component_id = $source->getPluginID();
    $label = $source->label();

    if ($source instanceof SourceWithChoicesInterface) {
      $component_id = $source->getChoice($data['source']);
      $result = $this->slotSourceProxy->getLabelWithSummary($data, [], TRUE);
      $label = $result['label'] ?? $source->label();
    }

    $instance_id = $instance_id ?: $data['node_id'] ?? NULL;

    if (!$instance_id || !$component_id) {
      $this->logger->error(
        '[' . static::class . '::buildSingleComponent] missing component ID: @component_id or instance ID: @instance_id. <pre>' . \print_r($data, TRUE) . '</pre>',
        ['@instance_id' => $instance_id ?? 'NULL', '@component_id' => $component_id],
      );

      return NULL;
    }

    return [
      'component_id' => $component_id,
      'label' => $label,
      'instance_id' => $instance_id,
    ];
  }

  /**
   * Build renderable from state data.
   *
   * @param string $builder_id
   *   Display Builder ID.
   * @param string $instance_id
   *   The instance ID.
   * @param array $data
   *   The UI Patterns form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  protected function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): ?array {
    $instance_id = $instance_id ?: $data['node_id'] ?? NULL;

    if (!$instance_id) {
      return NULL;
    }

    $classes = ['db-block'];

    if (isset($data['source']['plugin_id'])) {
      $classes[] = 'db-block-' . \strtolower(Html::cleanCssIdentifier($data['source']['plugin_id']));
    }
    else {
      $classes[] = 'db-block-' . \strtolower(Html::cleanCssIdentifier($data['source_id']));
    }
    $build = $this->renderSource($data, $classes);
    $is_empty = FALSE;

    if (isset($data['source_id']) && $data['source_id'] === 'token') {
      if (isset($build['content']) && empty($build['content'])) {
        $is_empty = TRUE;
      }
    }

    if (($data['source']['plugin_id'] ?? '') === 'system_messages_block') {
      // system_messages_block is never empty, but often invisible.
      // See: core/modules/system/src/Plugin/Block/SystemMessagesBlock.php
      // See: core/lib/Drupal/Core/Render/Element/StatusMessages.php
      // Let's always display it in a placeholder.
      $is_empty = TRUE;
    }

    $label_info = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);

    if (isset($data['source_id'])) {
      switch ($data['source_id']) {
        case 'entity_field':
          $label_info['summary'] = (string) $this->t('Field: @label', ['@label' => $label_info['label']]);

          break;

        case 'block':
          $label_info['summary'] = (string) $this->t('Block: @label', ['@label' => $label_info['summary']]);

          break;
      }
    }

    // This is the placeholder without configuration or content yet.
    if ($this->isEmpty($build) || $is_empty) {
      $build = $this->buildPlaceholderButton($label_info['summary']);
      // Highlight in the view to show it's a temporary block waiting for
      // configuration.
      $build['#attributes']['class'][] = 'db-background';
    }
    elseif (!$this->useAttributesVariable($build) || $this->hasMultipleRoot($build)) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => $classes],
        'content' => $build,
      ];
    }

    // This label is used for contextual menu.
    // @see assets/js/contextual_menu.js
    // The 'data-node-title' attribute is expected to contain a human-readable
    // label or summary describing the block instance. This value is usd in the
    // contextual menu for user actions such as edit, delete. The format should
    // be a plain string, typically the label or field summary.
    $build['#attributes']['data-node-title'] = $label_info['summary'] ?? $data['source_id'] ?? $data['node_id'] ?? '';
    $build['#attributes']['data-slot-position'] = $index;
    $build['#attributes']['data-instance-id'] = $instance_id;

    // Add data-node-type for easier identification of block types in JS or CSS.
    if (isset($data['source_id'])) {
      $build['#attributes']['data-node-type'] = $data['source_id'];
    }

    $build = $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $label_info['summary'] ?? $label_info['label'] ?? '', $index);

    return $build;
  }

  /**
   * Helper method to replace a specific instance in the DOM.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The builder instance.
   * @param string $node_id
   *   The node ID from the source tree.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function replaceNode(InstanceInterface $instance, string $node_id): array {
    $builder_id = (string) $instance->id();
    $parent_selector = '#' . $this->getHtmlId($builder_id) . ' [data-node-id="' . $node_id . '"]';
    $data = $instance->getNode($node_id);
    $build = [];
    $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

    try {
      $source = $this->sourceManager->createInstance(
        $data['source_id'],
        SourcePluginBase::buildConfiguration('slot', $slot_definition, $data, $this->configuration['contexts'] ?? [])
      );
    }
    catch (\Throwable $e) {
      $this->logger->error('Invalid source found: %message', ['%message' => $e->getMessage()]);

      return [];
    }

    if ($source instanceof SourceWithSlotsInterface) {
      $build = $this->buildSingleComponent($builder_id, $node_id, $source, $data);
    }
    else {
      $build = $this->buildSingleBlock($builder_id, $node_id, $data);
    }

    return $this->makeOutOfBand(
      $build ?? [],
      $parent_selector,
      'outerHTML'
    );
  }

  /**
   * Does the component use the attributes variable in template?
   *
   * @param array $renderable
   *   Component renderable.
   *
   * @return bool
   *   Use it or not.
   */
  protected function useAttributesVariable(array $renderable): bool {
    $random = \bin2hex(\random_bytes(8));
    $renderable['#attributes'][$random] = $random;
    $html = $this->renderer->renderInIsolation($renderable);

    return \str_contains((string) $html, $random);
  }

  /**
   * Get renderable array for a slot source.
   *
   * @param array $data
   *   The slot source data array containing:
   *   - source_id: The source ID
   *   - source: Array of source configuration.
   * @param array $classes
   *   (Optional) Classes to use to wrap the rendered source if needed.
   *
   * @return array
   *   The renderable array for this slot source.
   */
  protected function renderSource(array $data, array $classes = []): array {
    $build = $this->componentElementBuilder->buildSource([], 'content', [], $data, $this->configuration['contexts'] ?? []) ?? [];
    $build = $build['#slots']['content'][0] ?? [];

    // Fixes for token which is simple markup or html.
    if (isset($data['source_id']) && $data['source_id'] !== 'token') {
      return $build;
    }

    // If token is only markup, we don't have a wrapper, add it like styles
    // so the placeholder can be styled.
    if (!isset($build['#type'])) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => $classes],
        'content' => $build,
      ];
    }

    // If a style is applied, we have a wrapper from styles with classes, to
    // avoid our placeholder classes to be replaced we need to wrap it.
    elseif (isset($build['#attributes'])) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => $classes],
        'content' => $build,
      ];
    }

    return $build;
  }

  /**
   * Build builder renderable, recursively.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param array $data
   *   The current 'slice' of data.
   *
   * @return array
   *   A renderable array.
   */
  protected function digFromSlot(string $builder_id, array $data): array {
    $renderable = [];
    $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

    foreach ($data as $index => $source) {
      if (!isset($source['source_id'])) {
        continue;
      }

      try {
        $source_plugin = $this->sourceManager->createInstance(
          $source['source_id'],
          SourcePluginBase::buildConfiguration('slot', $slot_definition, $source, $this->configuration['contexts'] ?? [])
        );
      }
      catch (\Throwable $e) {
        $this->logger->error('Invalid source found: %message', ['%message' => $e->getMessage()]);

        continue;
      }

      if ($source_plugin instanceof SourceWithSlotsInterface) {
        $component = $this->buildSingleComponent($builder_id, '', $source_plugin, $source, $index);

        if ($component) {
          $renderable[$index] = $component;
        }

        continue;
      }

      $block = $this->buildSingleBlock($builder_id, '', $source, $index);

      if ($block) {
        $renderable[$index] = $block;
      }
    }

    return $renderable;
  }

  /**
   * Check if a renderable has multiple HTML root elements once rendered.
   *
   * @param array $renderable
   *   The renderable array to check.
   *
   * @return bool
   *   TRUE if the rendered output has multiple root elements, FALSE otherwise.
   */
  private function hasMultipleRoot(array $renderable): bool {
    $html = (string) $this->renderer->renderInIsolation($renderable);
    $dom = new HTML5(['disable_html_ns' => TRUE, 'encoding' => 'UTF-8']);
    $dom = $dom->loadHTMLFragment($html);

    return $dom->childElementCount > 1;
  }

  /**
   * Check if a renderable array is empty.
   *
   * @param array $renderable
   *   The renderable array to check.
   *
   * @return bool
   *   TRUE if the rendered output is empty, FALSE otherwise.
   */
  private function isEmpty(array $renderable): bool {
    $html = $this->renderer->renderInIsolation($renderable);

    return empty(\trim((string) $html));
  }

  /**
   * Build a component slot with dropzone.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param string $slot_id
   *   The slot ID.
   * @param array $definition
   *   The slot definition.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   A renderable array for the slot.
   */
  private function buildComponentSlot(string $builder_id, SourceWithSlotsInterface $source, string $slot_id, array $definition, string $instance_id): array {
    $dropzone = [
      '#type' => 'component',
      '#component' => 'display_builder:dropzone',
      '#props' => [
        'title' => $definition['title'],
        'variant' => 'highlighted',
      ],
      '#attributes' => [
        // Required for JavaScript @see components/dropzone/dropzone.js.
        'data-db-id' => $builder_id,
        // Slot is needed for contextual menu paste.
        // @see assets/js/contextual_menu.js
        'data-slot-id' => $slot_id,
        'data-slot-title' => \ucfirst($definition['title']),
        'data-node-id' => $instance_id,
        'data-instance-id' => $instance_id . '_' . $slot_id,
      ],
    ];

    if ($sources = $source->getSlotValue($slot_id)) {
      $dropzone['#slots']['content'] = $this->digFromSlot($builder_id, $sources);
    }

    return $this->htmxEvents->onSlotDrop($dropzone, $builder_id, $this->getPluginID(), $instance_id, $slot_id);
  }

}
