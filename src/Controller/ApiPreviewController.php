<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\ui_patterns_library\StoryPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Returns preview responses for Display builder routes.
 */
class ApiPreviewController extends ControllerBase implements ContainerInjectionInterface {

  use RenderableBuilderTrait;

  /**
   * The Pattern preset storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $presetConfigStorage;

  public function __construct(
    #[Autowire(service: 'plugin.manager.component_story')]
    private StoryPluginManager $storyPluginManager,
    #[Autowire(service: 'plugin.manager.sdc')]
    private ComponentPluginManager $componentManager,
    private RendererInterface $renderer,
  ) {
    $this->presetConfigStorage = $this->entityTypeManager()->getStorage('pattern_preset');
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
    $build = $this->generateBlock($block_id);

    $html = $this->renderer->renderRoot($build);
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
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
    /** @var \Drupal\display_builder\PatternPresetInterface $preset */
    $preset = $this->presetConfigStorage->load($preset_id);
    $data = $preset->getSources([], FALSE);

    $build = $this->renderSource($data);

    $html = $this->renderer->renderRoot($build);
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
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
        $first_story = reset($stories);
        $story[$first_story['machineName'] ?? 'default'] = $first_story;
        $build = $this->generateStory($component_id, $variant_id, $story);
      }
    }

    $html = $this->renderer->renderRoot($build);
    $response = new HtmlResponse();
    $response->setContent($html);

    return $response;
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

    foreach (array_keys($stories) as $story_id) {
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
    $definition = $this->componentManager->getDefinition($component_id);
    $html = [
      '#type' => 'component',
      '#component' => $component_id,
    ];
    foreach ($definition['slots'] ?? [] as $slot_id => $slot) {
      if (isset($slot['examples']) && is_array($slot['examples']) && !empty($slot['examples'])) {
        $html['#slots'][$slot_id] = $slot['examples'][0];
      }
    }
    foreach ($definition['props']['properties'] ?? [] as $prop_id => $prop) {
      if (isset($prop['examples']) && is_array($prop['examples']) && !empty($prop['examples'])) {
        $html['#props'][$prop_id] = $prop['examples'][0];
      }
    }

    return $html;
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
    $build = $builder->buildSource([], 'content', [], $data, []) ?? [];

    return $build['#slots']['content'][0] ?? [];
  }

}
