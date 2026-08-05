<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\ui_patterns_library\StoryPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns preview responses for Display builder routes.
 */
class ApiPreviewController extends ControllerBase {

  use RenderableBuilderTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.component_story')]
    private StoryPluginManager $storyPluginManager,
    #[Autowire(service: 'plugin.manager.sdc')]
    private ComponentPluginManager $componentManager,
    private RendererInterface $renderer,
    private IslandPluginManagerInterface $islandPluginManager,
  ) {}

  /**
   * Renders the Preview island of an instance in isolation, for its iframe.
   *
   * The single endpoint every buildable previews through - a standalone
   * component, a pattern preset, a Views display, an entity view mode, a page
   * layout. This is what the iframe loads: just the Preview island's rendered
   * display, on a bare full page, so it gets its own viewport (and reflows with
   * the viewport switcher) but none of the builder or site chrome.
   *
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   The instance to preview.
   *
   * @return array
   *   A render array of the previewed display.
   *
   * @see \Drupal\display_builder\Plugin\display_builder\Island\PreviewPanel::build()
   */
  public function getDisplayPreview(InstanceInterface $display_builder_instance): array {
    $profile = $display_builder_instance->getProfile();

    if ($profile === NULL || !isset($profile->getEnabledIslands()['preview'])) {
      throw new NotFoundHttpException();
    }

    $contexts = $display_builder_instance->getAvailableContexts();
    $definitions = \array_intersect_key($this->islandPluginManager->getDefinitions(), ['preview' => TRUE]);
    $islands = $this->islandPluginManager->createInstances($definitions, $contexts, $profile->getIslandConfigurations());
    $island = $islands['preview'] ?? NULL;

    if ($island === NULL) {
      throw new NotFoundHttpException();
    }

    return [
      $island->build($display_builder_instance, $display_builder_instance->getCurrentState(), ['in_iframe' => TRUE]),
      // The draft render is per-editor and must always be current.
      '#cache' => [
        'tags' => $display_builder_instance->getCacheTags(),
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Get block preview.
   *
   * @param string $block_id
   *   Block ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function getBlockPreview(string $block_id): HtmlResponse {
    return $this->buildResponse($this->generateBlock($block_id));
  }

  /**
   * Get preset preview.
   *
   * @param string $preset_id
   *   Preset ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function getPresetPreview(string $preset_id): HtmlResponse {
    /** @var \Drupal\display_builder\Entity\PatternPresetInterface $preset */
    $preset = $this->entityTypeManager()->getStorage('pattern_preset')->load($preset_id);
    $data = $preset->getSources([], FALSE);

    return $this->buildResponse($this->renderSource($data), $preset->get('description'));
  }

  /**
   * Get component preview, only HTML without specific css or js.
   *
   * @param string $component_id
   *   Component ID.
   * @param string $variant_id
   *   Component variant ID.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   */
  public function getComponentPreview(string $component_id, string $variant_id): HtmlResponse {
    $ui_patterns_library = $this->moduleHandler()->moduleExists('ui_patterns_library');

    $build = [];

    if (!$ui_patterns_library) {
      $build = $this->generateComponent($component_id);
    }
    else {
      $stories = $this->storyPluginManager->getComponentStories($component_id);

      if (empty($stories)) {
        $build = $this->generateComponent($component_id);
      }
      else {
        $story = [];
        $first_story = \reset($stories);
        $story[$first_story['machineName'] ?? 'default'] = $first_story;
        $build = $this->generateStory($component_id, $variant_id, $story);
      }
    }

    return $this->buildResponse($build, $this->getComponentDescription($component_id));
  }

  /**
   * Build renderable block.
   *
   * @param string $block_id
   *   The block id to preview.
   *
   * @return array
   *   A renderable array.
   */
  protected function generateBlock(string $block_id): array {
    $data = [
      'source_id' => 'block',
      'source' => [
        'plugin_id' => $block_id,
      ],
    ];

    return $this->renderSource($data);
  }

  /**
   * Build the preview response, with an optional description footer.
   *
   * The rendered thing and its description are wrapped separately so the
   * description can stay pinned at the bottom of the popup while the render
   * above it absorbs the clipping, whenever the popup had to be capped to
   * the room left on screen.
   *
   * @param array $build
   *   The renderable to preview.
   * @param string|null $description
   *   (Optional) Description to show below it, when there is one.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   The HTML response.
   *
   * @see components/display_builder/css/preview.css
   */
  private function buildResponse(array $build, ?string $description = NULL): HtmlResponse {
    $wrapper = [
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['db-preview__content']],
        'content' => $build,
      ],
    ];

    if ($description !== NULL && \trim($description) !== '') {
      $wrapper['description'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['db-preview__description']],
        'text' => ['#plain_text' => $description],
      ];
    }

    $response = new HtmlResponse();
    $response->setContent($this->renderer->renderRoot($wrapper));

    return $response;
  }

  /**
   * Get a component description, if it declares one.
   *
   * @param string $component_id
   *   The component id.
   *
   * @return string|null
   *   The description, or NULL when the component has none or is unknown.
   */
  private function getComponentDescription(string $component_id): ?string {
    try {
      $definition = $this->componentManager->getDefinition($component_id);
    }
    catch (\Throwable $th) {
      return NULL;
    }

    $description = $definition['description'] ?? NULL;

    return $description === NULL ? NULL : (string) $description;
  }

  /**
   * Generate a story.
   *
   * @param string $component_id
   *   The component id.
   * @param string $variant_id
   *   The component variant id.
   * @param array $stories
   *   The stories.
   *
   * @return array
   *   The render array
   */
  private function generateStory(string $component_id, string $variant_id, array $stories): array {
    $html = [];

    foreach (\array_keys($stories) as $story_id) {
      $html[$story_id] = [
        '#type' => 'component',
        '#component' => $component_id,
        '#story' => $story_id,
        '#props' => ['variant' => $variant_id],
      ];
    }

    return $html;
  }

  /**
   * Generate a content even with empty story or no library module.
   *
   * @param string $component_id
   *   The component id.
   *
   * @return array
   *   The render array
   *
   * @todo Remove when https://www.drupal.org/project/ui_patterns/issues/3414774 is merged
   */
  private function generateComponent(string $component_id): array {
    try {
      $definition = $this->componentManager->getDefinition($component_id);
    }
    catch (\Throwable $th) {
      return [];
    }

    $html = [
      '#type' => 'component',
      '#component' => $component_id,
    ];

    foreach ($definition['slots'] ?? [] as $slot_id => $slot) {
      if (isset($slot['examples']) && \is_array($slot['examples']) && !empty($slot['examples'])) {
        $html['#slots'][$slot_id] = $slot['examples'][0];
      }
    }

    foreach ($definition['props']['properties'] ?? [] as $prop_id => $prop) {
      if (isset($prop['examples']) && \is_array($prop['examples']) && !empty($prop['examples'])) {
        $html['#props'][$prop_id] = $prop['examples'][0];
      }
    }

    return $html;
  }

  /**
   * Get renderable array for a slot source.
   *
   * @param array $data
   *   The slot source data array containing:
   *   - source_id: The source ID
   *   - source: Array of source configuration.
   *
   * @return array
   *   The renderable array for this slot source.
   */
  private function renderSource(array $data): array {
    /** @var \Drupal\ui_patterns\Element\ComponentElementBuilder $builder */
    $builder = \Drupal::service('ui_patterns.component_element_builder'); // @phpcs:ignore

    try {
      $build = $builder->buildSource([], 'content', [], $data, []) ?? [];

      return $build['#slots']['content'][0] ?? [];
    }
    catch (\Throwable $th) {
      return [];
    }
  }

}
