<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuilderHtmx;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared implementation for the View panels.
 *
 * Every View panel answers the same three questions the same way: how to walk
 * the source tree, what identity to stamp on a node and a slot so the
 * contextual menu and the drag-and-drop code can find them again, and what to
 * send back over htmx when a node changed. What differs is only how a single
 * node is drawn, which is why ::renderComponent() and ::buildSingleBlock() are
 * the two abstract methods here.
 *
 * How a node is drawn "for real", with its actual markup and live dropzones, is
 * not part of this: that is the Canvas's answer, and Scaffold borrows it for
 * the components it is configured to render.
 *
 * @see \Drupal\display_builder\Island\RealRenderTrait
 */
abstract class ViewPanelBase extends IslandPluginBase {

  use IslandReloadEventsTrait;

  /**
   * Proxy for slot source operations.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * The slot prop type definition, resolved on first use.
   */
  private ?array $slotDefinition = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->slotSourceProxy = $container->get('display_builder.slot_sources_proxy');

    return $instance;
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
   * Draws a single component node, the way this panel draws components.
   *
   * Called with the identity already resolved, so an implementation never has
   * to repeat the ::resolveComponentInfo() guard.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $data
   *   The UI Patterns form state data.
   * @param string $component_id
   *   The resolved component ID.
   * @param string $label
   *   The resolved human-readable label.
   * @param int $index
   *   The index of the component within its parent slot/root.
   *
   * @return array|null
   *   A renderable array.
   */
  abstract protected function renderComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, string $component_id, string $label, int $index): ?array;

  /**
   * Build renderable from state data.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   * @param array $data
   *   The UI Patterns form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  abstract protected function buildSingleBlock(InstanceInterface $instance, string $node_id, array $data, int $index = 0): ?array;

  /**
   * Resolves a component node's identity, then lets the panel draw it.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param string $node_id
   *   The tree node ID.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param array $data
   *   The UI Patterns form state data.
   * @param int $index
   *   (Optional) The index of the component. Default to 0.
   *
   * @return array|null
   *   A renderable array, or NULL if the node has no resolvable identity.
   */
  protected function buildSingleComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, int $index = 0): ?array {
    $info = $this->resolveComponentInfo($source, $data, $node_id);

    if ($info === NULL) {
      return NULL;
    }

    return $this->renderComponent($instance, $info['instance_id'], $source, $data, $info['component_id'], $info['label'], $index);
  }

  /**
   * Builds the root dropzone render array.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The Display Builder instance.
   * @param array $data
   *   The current 'slice' of data.
   *
   * @return array
   *   The root dropzone render array.
   */
  protected function buildRootDropzone(InstanceInterface $builder, array $data): array {
    $builder_id = (string) $builder->id();
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:dropzone',
      '#props' => [
        'variant' => 'root',
      ],
      '#slots' => [
        'content' => $this->digFromSlot($builder, $data),
      ],
      '#attributes' => [
        // Required for JavaScript @see components/dropzone/dropzone.js.
        'data-db-id' => $builder_id,
        'data-node-title' => $this->t('Root container'),
        'data-db-root' => TRUE,
        // Shown by dropzone.css when the dropzone is actually empty.
        'data-empty-hint' => $this->t('Nothing built yet. Open the Library (shortcut: l) and drop a component here.'),
      ],
    ];

    // For root dropzone, constraints can come from the buildable plugin.
    // Example: the cardinality of the field storage in EntityViewOverride.
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\PluginItem $field */
    $field = $builder->get('buildable')->first();

    if (!empty($field->getString())) {
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $field->getInstance();
      $build = $this->addDropzoneConstraints($build, $buildable, '');
    }

    return $this->htmxEvents->onRootDrop($build, $builder_id, $this->getPluginID());
  }

  /**
   * Add dropzone constraints.
   *
   * @param array $dropzone
   *   The already built dropzone render array.
   * @param \Drupal\display_builder\DisplayBuildableInterface|\Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source or the buildable.
   * @param string $slot_id
   *   Slot id.
   *
   * @return array
   *   The altered dropzone render array.
   */
  protected function addDropzoneConstraints(array $dropzone, DisplayBuildableInterface|SourceWithSlotsInterface $source, string $slot_id): array {
    $cardinality = ($source instanceof DisplayBuildableInterface) ? $source->getRootCardinality() : $source->getSlotCardinality($slot_id);

    if ($cardinality >= 0) {
      $dropzone['#attributes']['data-max-items'] = $cardinality;
    }

    return $dropzone;
  }

  /**
   * Stamps the node-identity attributes the contextual menu relies on.
   *
   * Resolves "what did I right-click" - shared across every View panel so a
   * new attribute only needs to be added here once.
   *
   * Also stamps `data-island-id`, the island that rendered this specific
   * node wrapper: Canvas, Scaffold, and Navigator all share the same Sortable
   * group (@see components/dropzone/dropzone.js), so a node can be dragged
   * from one panel's dropzone into another's. Sortable only relocates the
   * existing DOM node - it never re-renders it - so after a cross-panel
   * drop the moved element is still wearing its *source* panel's markup.
   * `display_builder.js`'s `addVals()` reads this attribute off the
   * dragged element to tell the server which island actually rendered it,
   * so `ApiController::attachToRoot()/attachToSlot()` can tell a
   * cross-panel move (destination island needs a fresh render, its own
   * markup differs) apart from a same-panel reorder (destination is
   * already correct, safe to skip).
   *
   * @param string $title
   *   Human-readable label for the instance.
   * @param int $index
   *   Position within its parent slot/root.
   * @param string|null $node_type
   *   (Optional) The source ID, for CSS/JS targeting of a given node type.
   *
   * @return array
   *   Attributes to merge into the instance wrapper's '#attributes'.
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  protected function buildNodeAttributes(string $title, int $index, ?string $node_type = NULL): array {
    $attributes = [
      'data-node-title' => $title,
      'data-slot-position' => $index,
      'data-island-id' => $this->getPluginID(),
    ];

    if ($node_type !== NULL) {
      $attributes['data-node-type'] = $node_type;
    }

    return $attributes;
  }

  /**
   * Stamps the slot-identity attributes the contextual menu relies on.
   *
   * Resolves "paste/duplicate into this slot" - shared across every View panel
   * so a new attribute only needs to be added here once.
   *
   * @param string $slot_id
   *   The slot ID.
   * @param string $slot_title
   *   The slot's human-readable title.
   * @param string $parent_node_id
   *   The node ID of the component owning this slot.
   * @param string $parent_title
   *   The human-readable label of the component owning this slot.
   *
   * @return array
   *   Attributes to merge into the slot dropzone/tree-item's '#attributes'.
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  protected function buildSlotAttributes(string $slot_id, string $slot_title, string $parent_node_id, string $parent_title): array {
    return [
      'data-slot-id' => $slot_id,
      'data-slot-title' => \ucfirst($slot_title),
      'data-node-id' => $parent_node_id,
      'data-node-title' => $parent_title,
    ];
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
    $source = $this->createSlotSource($data);

    if ($source === NULL) {
      return [];
    }

    if ($source instanceof SourceWithSlotsInterface) {
      $build = $this->buildSingleComponent($instance, $node_id, $source, $data);
    }
    else {
      $build = $this->buildSingleBlock($instance, $node_id, $data);
    }

    return DisplayBuilderHtmx::makeOutOfBand(
      $build ?? [],
      $parent_selector,
      'outerHTML'
    );
  }

  /**
   * Build builder renderable, recursively.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param array $data
   *   The current 'slice' of data.
   *
   * @return array
   *   A renderable array.
   */
  protected function digFromSlot(InstanceInterface $instance, array $data): array {
    $renderable = [];

    foreach ($data as $index => $source) {
      if (!isset($source['source_id'])) {
        continue;
      }

      $source_plugin = $this->createSlotSource($source);

      if ($source_plugin === NULL) {
        continue;
      }

      if ($source_plugin instanceof SourceWithSlotsInterface) {
        $component = $this->buildSingleComponent($instance, '', $source_plugin, $source, $index);

        if ($component) {
          $renderable[$index] = $component;
        }

        continue;
      }

      $block = $this->buildSingleBlock($instance, '', $source, $index);

      if ($block) {
        $renderable[$index] = $block;
      }
    }

    return $renderable;
  }

  /**
   * Resolves component ID, label, and instance ID from source and data.
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
  private function resolveComponentInfo(SourceWithSlotsInterface $source, array $data, string $instance_id): ?array {
    $component_id = $source->getPluginID();
    $label = $source->label();

    if (($source instanceof SourceWithChoicesInterface) && isset($data['source'])) {
      $component_id = $source->getChoice($data['source']);
      $result = $this->slotSourceProxy->getLabelWithSummary($data, [], TRUE);
      $label = $result['label'] ?? $source->label();
    }

    $instance_id = $instance_id ?: $data['node_id'] ?? NULL;

    if (!$instance_id || !$component_id) {
      $this->logger->error(
        '[' . static::class . '::renderComponent] missing component ID: @component_id or instance ID: @instance_id. <pre>' . \print_r($data, TRUE) . '</pre>',
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
   * Instantiates the source plugin backing one tree node.
   *
   * @param array $data
   *   The UI Patterns form state data for the node.
   *
   * @return object|null
   *   The source plugin, or NULL if it could not be instantiated. Callers
   *   narrow it themselves with SourceWithSlotsInterface, which is the only
   *   distinction any of them makes.
   */
  private function createSlotSource(array $data): ?object {
    // The slot prop type is stateless and identical for every node, so resolve
    // it once instead of on each of the O(nodes) calls this method takes.
    $this->slotDefinition ??= ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

    try {
      return $this->sourceManager->createInstance(
        $data['source_id'],
        SourcePluginBase::buildConfiguration('slot', $this->slotDefinition, $data, $this->configuration['contexts'] ?? [])
      );
    }
    catch (\Throwable $e) {
      $this->logger->error('Invalid source found: %message', ['%message' => $e->getMessage()]);

      return NULL;
    }
  }

}
