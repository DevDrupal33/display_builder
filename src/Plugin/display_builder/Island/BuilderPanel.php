<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandBuilderInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_styles\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builder island plugin implementation.
 */
#[Island(
  id: 'builder',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Builder'),
  description: new TranslatableMarkup('The Display Builder main island.'),
  type: IslandType::View,
  icon: 'tools',
)]
class BuilderPanel extends IslandPluginBase implements IslandBuilderInterface {

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
      'b' => t('Show the builder'),
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
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->replaceInstance($builder_id, $parent_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->replaceInstance($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    if (empty($parent_id)) {
      return $this->reloadWithGlobalData($builder_id);
    }

    return $this->replaceInstance($builder_id, $parent_id);
  }

  /**
   * {@inheritdoc}
   */
  public function buildSingleComponent(string $builder_id, string $instance_id, array $data, int $index = 0): ?array {
    $component_id = $data['source']['component']['component_id'] ?? NULL;
    $instance_id = $instance_id ?: $data['node_id'];

    if (!$instance_id && !$component_id) {
      return NULL;
    }

    $component = $this->sdcManager->getDefinition($component_id);

    if (!$component) {
      return NULL;
    }

    $build = $this->renderSource($data);
    // Required for the context menu label.
    // @see assets/js/contextual_menu.js
    $build['#attributes']['data-node-title'] = $component['label'];
    $build['#attributes']['data-slot-position'] = $index;

    foreach ($component['slots'] ?? [] as $slot_id => $definition) {
      $build['#slots'][$slot_id] = $this->buildComponentSlot($builder_id, $slot_id, $definition, $data, $instance_id);
      // Prevent the slot to be generated again.
      unset($build['#ui_patterns']['slots'][$slot_id]);
    }

    if ($this->isEmpty($build)) {
      // Keep the placeholder if the component is not renderable.
      $message = $component['name'] . ': ' . $this->t('Empty by default. Configure it to make it visible');
      $build = $this->buildPlaceholder($message);
    }

    if (!$this->useAttributesVariable($build)) {
      $build = $this->wrapContent($build);
    }

    return $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $component['label'], $index);
  }

  /**
   * {@inheritdoc}
   */
  public function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): ?array {
    $instance_id = $instance_id ?: $data['node_id'];

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
    elseif (!Element::isAcceptingAttributes($build)) {
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

    $build = $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $label_info['summary'] ?? $label_info['label'] ?? '', $index);

    return $build;
  }

  /**
   * Helper method to replace a specific instance in the DOM.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function replaceInstance(string $builder_id, string $instance_id): array {
    $parent_selector = '#' . $this->getHtmlId($builder_id) . ' [data-node-id="' . $instance_id . '"]';
    // @todo pass \Drupal\display_builder\InstanceInterface object in
    // parameters instead of loading again.
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
    $data = $builder->get($instance_id);

    $build = [];

    if (isset($data['source_id']) && $data['source_id'] === 'component') {
      $build = $this->buildSingleComponent($builder_id, $instance_id, $data);
    }
    else {
      $build = $this->buildSingleBlock($builder_id, $instance_id, $data);
    }

    return $this->makeOutOfBand(
      $build,
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
    $random = \uniqid();
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

    // If token is only markup, we don't have a wrapper, add it like ui_styles
    // so the placeholder can be styled.
    if (!isset($build['#type'])) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => $classes],
        'content' => $build,
      ];
    }

    // If a style is applied, we have a wrapper from ui_styles with classes, to
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

    foreach ($data as $index => $source) {
      if (!isset($source['source_id'])) {
        continue;
      }

      if ($source['source_id'] === 'component') {
        $component = $this->buildSingleComponent($builder_id, '', $source, $index);

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
   * @param string $slot_id
   *   The slot ID.
   * @param array $definition
   *   The slot definition.
   * @param array $data
   *   The component data.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   A renderable array for the slot.
   */
  private function buildComponentSlot(string $builder_id, string $slot_id, array $definition, array $data, string $instance_id): array {
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
      ],
    ];

    if (isset($data['source']['component']['slots'][$slot_id]['sources'])) {
      $sources = $data['source']['component']['slots'][$slot_id]['sources'];
      $dropzone['#slots']['content'] = $this->digFromSlot($builder_id, $sources);
    }

    return $this->htmxEvents->onSlotDrop($dropzone, $builder_id, $this->getPluginID(), $instance_id, $slot_id);
  }

}
