<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_styles\Render\Element;
use Masterminds\HTML5;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for Display Builder build content.
 *
 * Returns JSON with HTML, CSS URLs, and JS URLs for Shadow DOM injection.
 * Rendered with frontend theme for correct markup.
 */
class BuildController extends ControllerBase {

  use RenderableBuilderTrait;

  /**
   * The island ID used for HTMX events.
   */
  protected const ISLAND_ID = 'builder';

  /**
   * Constructs a BuildController.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Asset\AssetResolverInterface $assetResolver
   *   The asset resolver service.
   * @param \Drupal\display_builder\HtmxEvents $htmxEvents
   *   The HTMX events service.
   * @param \Drupal\Core\Theme\ComponentPluginManager $sdcManager
   *   The SDC plugin manager.
   * @param \Drupal\ui_patterns\Element\ComponentElementBuilder $componentElementBuilder
   *   The component element builder.
   * @param \Drupal\display_builder\SlotSourceProxy $slotSourceProxy
   *   The slot source proxy.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   *   The library discovery service.
   */
  public function __construct(
    #[Autowire(service: 'renderer')]
    protected RendererInterface $renderer,
    #[Autowire(service: 'asset.resolver')]
    protected AssetResolverInterface $assetResolver,
    #[Autowire(service: 'display_builder.htmx_events')]
    protected HtmxEvents $htmxEvents,
    #[Autowire(service: 'plugin.manager.sdc')]
    protected ComponentPluginManager $sdcManager,
    #[Autowire(service: 'ui_patterns.component_element_builder')]
    protected ComponentElementBuilder $componentElementBuilder,
    #[Autowire(service: 'display_builder.slot_sources_proxy')]
    protected SlotSourceProxy $slotSourceProxy,
    #[Autowire(service: 'theme.manager')]
    protected ThemeManagerInterface $themeManager,
    #[Autowire(service: 'library.discovery')]
    protected LibraryDiscoveryInterface $libraryDiscovery,
  ) {}

  /**
   * Returns the build content as JSON for Shadow DOM injection.
   *
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   The Display Builder instance.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with html, css, js, and settings.
   */
  public function build(InstanceInterface $display_builder_instance): JsonResponse {
    // Get the current state (list of sources).
    $sources = $display_builder_instance->getCurrentState();
    $builder_id = (string) $display_builder_instance->id();

    // Build the content from sources using BuilderPanelBase logic.
    $build = $this->buildDropzoneContent($builder_id, $sources, $display_builder_instance);

    // Render in a new context to capture all bubbled-up metadata.
    $context = new RenderContext();
    $html = $this->renderer->executeInRenderContext($context, function () use (&$build) {
      return (string) $this->renderer->render($build);
    });

    // Get attached assets from the bubbled metadata.
    $attached = [];
    if (!$context->isEmpty()) {
      $bubbled = $context->pop();
      $attached = $bubbled->getAttachments();
    }

    $assets = $this->resolveAssets($attached);

    return new JsonResponse([
      'html' => $html,
      'css' => $assets['css'],
      'js' => $assets['js'],
      'settings' => $attached['drupalSettings'] ?? [],
    ]);
  }

  /**
   * Builds the dropzone content for the builder.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $sources
   *   The sources from the instance state.
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildDropzoneContent(string $builder_id, array $sources, InstanceInterface $instance): array {
    $contexts = $instance->getContexts() ?? [];
    $content = $this->digFromSlot($builder_id, $sources, $contexts);

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:dropzone',
      '#props' => [
        'variant' => 'root',
      ],
      '#slots' => [
        'content' => $content,
      ],
      '#attributes' => [
        'data-db-id' => $builder_id,
        'data-node-title' => $this->t('Base container'),
        'data-db-root' => TRUE,
      ],
    ];

    // Add HTMX events for root dropzone drag-and-drop.
    return $this->htmxEvents->onRootDrop($build, $builder_id, self::ISLAND_ID);
  }

  /**
   * Build builder renderable, recursively.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param array $data
   *   The current 'slice' of data.
   * @param array $contexts
   *   The context objects.
   *
   * @return array
   *   A renderable array.
   */
  protected function digFromSlot(string $builder_id, array $data, array $contexts): array {
    $renderable = [];

    foreach ($data as $index => $source) {
      if (!isset($source['source_id'])) {
        continue;
      }

      if ($source['source_id'] === 'component') {
        $component = $this->buildSingleComponent($builder_id, '', $source, $contexts, $index);

        if ($component) {
          $renderable[$index] = $component;
        }

        continue;
      }

      $block = $this->buildSingleBlock($builder_id, '', $source, $contexts, $index);

      if ($block) {
        $renderable[$index] = $block;
      }
    }

    return $renderable;
  }

  /**
   * Build renderable from state data for a component.
   *
   * @param string $builder_id
   *   Display Builder ID.
   * @param string $instance_id
   *   The instance ID.
   * @param array $data
   *   The UI Patterns form state data.
   * @param array $contexts
   *   The context objects.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  protected function buildSingleComponent(string $builder_id, string $instance_id, array $data, array $contexts, int $index = 0): ?array {
    $component_id = $data['source']['component']['component_id'] ?? NULL;
    $instance_id = $instance_id ?: $data['node_id'];

    if (!$instance_id && !$component_id) {
      return NULL;
    }

    $component = $this->sdcManager->getDefinition($component_id);

    if (!$component) {
      return NULL;
    }

    $build = $this->renderSource($data, $contexts);
    // Required for the context menu label.
    $build['#attributes']['data-node-title'] = $component['label'];
    $build['#attributes']['data-slot-position'] = $index;

    foreach ($component['slots'] ?? [] as $slot_id => $definition) {
      $build['#slots'][$slot_id] = $this->buildComponentSlot($builder_id, $slot_id, $definition, $data, $instance_id, $contexts);
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
   * Build renderable from state data for a block.
   *
   * @param string $builder_id
   *   Display Builder ID.
   * @param string $instance_id
   *   The instance ID.
   * @param array $data
   *   The UI Patterns form state data.
   * @param array $contexts
   *   The context objects.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  protected function buildSingleBlock(string $builder_id, string $instance_id, array $data, array $contexts, int $index = 0): ?array {
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
    $build = $this->renderSource($data, $contexts, $classes);
    $is_empty = FALSE;

    if (isset($data['source_id']) && $data['source_id'] === 'token') {
      if (isset($build['content']) && empty($build['content'])) {
        $is_empty = TRUE;
      }
    }

    if (($data['source']['plugin_id'] ?? '') === 'system_messages_block') {
      $is_empty = TRUE;
    }

    $label_info = $this->slotSourceProxy->getLabelWithSummary($data, $contexts);

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
    elseif (!Element::isAcceptingAttributes($build) || $this->hasMultipleRoot($build)) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => $classes],
        'content' => $build,
      ];
    }

    // This label is used for contextual menu.
    $build['#attributes']['data-node-title'] = $label_info['summary'] ?? $data['source_id'] ?? $data['node_id'] ?? '';
    $build['#attributes']['data-slot-position'] = $index;

    // Add data-node-type for easier identification of block types in JS or CSS.
    if (isset($data['source_id'])) {
      $build['#attributes']['data-node-type'] = $data['source_id'];
    }

    $build = $this->htmxEvents->onInstanceClick($build, $builder_id, $instance_id, $label_info['summary'] ?? $label_info['label'] ?? '', $index);

    return $build;
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
   * @param array $contexts
   *   The context objects.
   *
   * @return array
   *   A renderable array for the slot.
   */
  protected function buildComponentSlot(string $builder_id, string $slot_id, array $definition, array $data, string $instance_id, array $contexts): array {
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
        'data-slot-id' => $slot_id,
        'data-slot-title' => \ucfirst($definition['title']),
        'data-node-id' => $instance_id,
      ],
    ];

    if (isset($data['source']['component']['slots'][$slot_id]['sources'])) {
      $sources = $data['source']['component']['slots'][$slot_id]['sources'];
      $dropzone['#slots']['content'] = $this->digFromSlot($builder_id, $sources, $contexts);
    }

    return $this->htmxEvents->onSlotDrop($dropzone, $builder_id, self::ISLAND_ID, $instance_id, $slot_id);
  }

  /**
   * Get renderable array for a slot source.
   *
   * @param array $data
   *   The slot source data array containing:
   *   - source_id: The source ID
   *   - source: Array of source configuration.
   * @param array $contexts
   *   The context objects.
   * @param array $classes
   *   (Optional) Classes to use to wrap the rendered source if needed.
   *
   * @return array
   *   The renderable array for this slot source.
   */
  protected function renderSource(array $data, array $contexts, array $classes = []): array {
    $build = $this->componentElementBuilder->buildSource([], 'content', [], $data, $contexts) ?? [];
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
   * Check if a renderable has multiple HTML root elements once rendered.
   *
   * @param array $renderable
   *   The renderable array to check.
   *
   * @return bool
   *   TRUE if the rendered output has multiple root elements, FALSE otherwise.
   */
  protected function hasMultipleRoot(array $renderable): bool {
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
  protected function isEmpty(array $renderable): bool {
    $html = $this->renderer->renderInIsolation($renderable);

    return empty(\trim((string) $html));
  }

  /**
   * Resolves attached assets to CSS and JS URLs.
   *
   * Includes both the attached libraries from rendered content AND
   * the global libraries from the active frontend theme.
   *
   * @param array $attached
   *   The attached array from render.
   *
   * @return array
   *   Array with 'css' and 'js' URL arrays.
   */
  protected function resolveAssets(array $attached): array {
    $css_urls = [];
    $js_urls = [];

    // Start with libraries from rendered content.
    $libraries = $attached['library'] ?? [];

    // Add the active theme's libraries.
    $theme_libraries = $this->getThemeLibraries();
    $libraries = \array_merge($theme_libraries, $libraries);

    if (empty($libraries)) {
      return ['css' => $css_urls, 'js' => $js_urls];
    }

    // Create AttachedAssets object with all libraries.
    $assets = new AttachedAssets();
    $assets->setLibraries(\array_unique($libraries));

    // Resolve CSS assets.
    $css_assets = $this->assetResolver->getCssAssets($assets, FALSE, \Drupal::languageManager()->getCurrentLanguage());
    foreach ($css_assets as $css_asset) {
      if (isset($css_asset['data']) && \is_string($css_asset['data'])) {
        // Normalize the path by resolving ../ segments.
        $path = $this->normalizePath($css_asset['data']);
        $css_urls[] = \base_path() . $path;
      }
    }

    // Resolve JS assets.
    [$js_assets_header, $js_assets_footer] = $this->assetResolver->getJsAssets($assets, FALSE, \Drupal::languageManager()->getCurrentLanguage());
    $all_js = \array_merge($js_assets_header, $js_assets_footer);

    foreach ($all_js as $js_asset) {
      if (isset($js_asset['data']) && \is_string($js_asset['data'])) {
        // Skip inline scripts.
        if ($js_asset['type'] ?? '' === 'inline') {
          continue;
        }
        $path = $this->normalizePath($js_asset['data']);
        $js_urls[] = \base_path() . $path;
      }
    }

    return ['css' => $css_urls, 'js' => $js_urls];
  }

  /**
   * Gets the libraries that should be loaded for the active theme.
   *
   * @return array
   *   Array of library names (e.g., 'theme_name/global-styling').
   */
  protected function getThemeLibraries(): array {
    $libraries = [];

    // Get the active theme name.
    $active_theme = $this->themeManager->getActiveTheme();
    $theme_name = $active_theme->getName();

    // Get all libraries defined by the theme.
    $theme_libraries = $this->libraryDiscovery->getLibrariesByExtension($theme_name);

    // Add commonly used global libraries.
    // Most themes have 'global-styling' or 'global-scripts'.
    $global_library_names = [
      'global-styling',
      'global-scripts',
      'base',
      'global',
    ];

    foreach ($global_library_names as $lib_name) {
      if (isset($theme_libraries[$lib_name])) {
        $libraries[] = $theme_name . '/' . $lib_name;
      }
    }

    // Also check for libraries defined in the theme's .info.yml as 'libraries'.
    // These are automatically attached to all pages using the theme.
    $theme_info = $active_theme->getExtension();
    if ($theme_info && method_exists($theme_info, 'info')) {
      $info = $theme_info->info;
      if (!empty($info['libraries'])) {
        foreach ($info['libraries'] as $library) {
          $libraries[] = $library;
        }
      }
    }

    // Add all libraries from the base themes as well.
    foreach ($active_theme->getBaseThemeExtensions() as $base_theme) {
      $base_theme_name = $base_theme->getName();
      $base_libraries = $this->libraryDiscovery->getLibrariesByExtension($base_theme_name);

      foreach ($global_library_names as $lib_name) {
        if (isset($base_libraries[$lib_name])) {
          $libraries[] = $base_theme_name . '/' . $lib_name;
        }
      }
    }

    return $libraries;
  }

  /**
   * Normalizes a path by resolving ../ segments.
   *
   * @param string $path
   *   The path to normalize.
   *
   * @return string
   *   The normalized path.
   */
  protected function normalizePath(string $path): string {
    $parts = \explode('/', $path);
    $result = [];

    foreach ($parts as $part) {
      if ($part === '..') {
        \array_pop($result);
      }
      elseif ($part !== '' && $part !== '.') {
        $result[] = $part;
      }
    }

    return \implode('/', $result);
  }

}

