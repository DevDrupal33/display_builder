<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Core\Render\RendererInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Masterminds\HTML5;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a node with its real output, slots wired as live dropzones.
 *
 * The Canvas's way of drawing a component: the component's actual SDC or block
 * markup, with each of its slots replaced by a real, draggable dropzone, as
 * opposed to the schematic card the Scaffold draws.
 *
 * A trait rather than a base class because its two users are siblings under
 * ViewPanelBase, neither one a parent of the other: the Canvas draws everything
 * this way, while Scaffold draws only its configured allowlist of layout
 * components this way and leaves everything else a schematic card.
 *
 * Users must be a ViewPanelBase subclass - the methods here call back into
 * ::digFromSlot(), ::buildNodeAttributes() and ::buildSlotAttributes() - and
 * must call ::initRealRender() from their own ::create().
 *
 * @see \Drupal\display_builder\Plugin\display_builder\Island\ViewPanelBase
 */
trait RealRenderTrait {

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * The component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * Injects what rendering for real needs, on top of an island's own services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  protected function initRealRender(ContainerInterface $container): void {
    $this->renderer = $container->get('renderer');
    $this->componentElementBuilder = $container->get('ui_patterns.component_element_builder');
  }

  /**
   * Renders a component with its real output, slots wired as live dropzones.
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
   *   The resolved component ID (@see ViewPanelBase::resolveComponentInfo()).
   * @param string $label
   *   The resolved label (@see ViewPanelBase::resolveComponentInfo()).
   * @param int $index
   *   The index of the component within its parent slot/root.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildComponentRealRender(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, string $component_id, string $label, int $index): array {
    $build = $this->renderSource($data);
    $build['#attributes'] = \array_merge($build['#attributes'] ?? [], $this->buildNodeAttributes($label, $index));
    $build['#attributes']['data-testid'] = $component_id;

    foreach ($source->getSlotDefinitions() as $slot_id => $definition) {
      $slot = $this->buildComponentSlot($instance, $source, $slot_id, $definition, $node_id);
      $build = $source->setSlotRenderable($build, $slot_id, $slot);
    }

    if ($this->isRenderEmptyOrFailing($this->renderer, $build)) {
      // Keep the placeholder if the component is not renderable.
      $message = $component_id . ': ' . $this->t('Empty by default. Configure it to make it visible');
      $build = $this->buildPlaceholder($message);
    }

    if (!$this->useAttributesVariable($build)) {
      $build = $this->wrapContent($build);
    }

    return $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $source->label(), $index);
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

    // A token that is only markup has no wrapper of its own, and a token a
    // style was applied to has one carrying the style classes. Either way, wrap
    // it so the placeholder classes can be styled without replacing anything.
    if (!isset($build['#type']) || isset($build['#attributes'])) {
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
   * Build a component slot with dropzone.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance ID.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param string $slot_id
   *   The slot ID.
   * @param array $definition
   *   The slot definition.
   * @param string $node_id
   *   The node id of the source.
   * @param string|null $parent_title
   *   (Optional) Label of the component owning the slot. Defaults to the source
   *   plugin label; the Scaffold passes the resolved label instead, which
   *   differs for a source with choices.
   *
   * @return array
   *   A renderable array for the slot.
   */
  private function buildComponentSlot(InstanceInterface $instance, SourceWithSlotsInterface $source, string $slot_id, array $definition, string $node_id, ?string $parent_title = NULL): array {
    $builder_id = (string) $instance->id();
    $dropzone = [
      '#type' => 'component',
      '#component' => 'display_builder:dropzone',
      '#attributes' => \array_merge(
        [
          // Required for JavaScript @see components/dropzone/dropzone.js.
          'data-db-id' => $builder_id,
          'data-testid' => 'dropzone_' . $slot_id,
        ],
        $this->buildSlotAttributes($slot_id, $definition['title'], $node_id, $parent_title ?? $source->label())
      ),
    ];

    if ($sources = $source->getSlotValue($slot_id)) {
      $dropzone['#slots']['content'] = $this->digFromSlot($instance, $sources);
    }

    return $this->htmxEvents->onSlotDrop($dropzone, $builder_id, $this->getPluginID(), $node_id, $slot_id);
  }

}
