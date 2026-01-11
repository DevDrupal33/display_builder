<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\RenderableBuilderTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Display Builder preview iframe content.
 *
 * Renders the current state of a Display Builder instance using
 * the default frontend theme, suitable for embedding in an iframe.
 */
class PreviewController extends ControllerBase {

  use RenderableBuilderTrait;

  /**
   * Constructs a PreviewController.
   *
   * @param \Drupal\Core\Render\BareHtmlPageRendererInterface $bareHtmlPageRenderer
   *   The bare HTML page renderer.
   */
  public function __construct(
    #[Autowire(service: 'bare_html_page_renderer')]
    protected BareHtmlPageRendererInterface $bareHtmlPageRenderer,
  ) {}

  /**
   * Renders the preview content for a Display Builder instance.
   *
   * @param \Drupal\display_builder\InstanceInterface $display_builder_instance
   *   The Display Builder instance.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The rendered preview as a full HTML page.
   */
  public function preview(InstanceInterface $display_builder_instance): Response {
    // Get the current state (list of sources).
    $sources = $display_builder_instance->getCurrentState();

    // Build the content from sources.
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['display-builder-preview-content'],
        'data-db-preview' => $display_builder_instance->id(),
      ],
    ];

    if (!empty($sources)) {
      $build['content'] = $this->renderSources($sources, $display_builder_instance);
    }
    else {
      $build['empty'] = [
        '#markup' => $this->t('No content yet. Add components in the builder.'),
        '#prefix' => '<p class="display-builder-preview-empty">',
        '#suffix' => '</p>',
      ];
    }

    // Render as a bare HTML page with the frontend theme.
    $response = $this->bareHtmlPageRenderer->renderBarePage(
      $build,
      (string) $this->t('Preview'),
      'page',
    );

    return $response;
  }

  /**
   * Renders the sources from a Display Builder instance.
   *
   * @param array $sources
   *   The sources array from the instance state.
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The Display Builder instance.
   *
   * @return array
   *   A renderable array.
   */
  protected function renderSources(array $sources, InstanceInterface $instance): array {
    $build = [];
    $contexts = $instance->getContexts() ?? [];

    foreach ($sources as $index => $source) {
      $rendered = $this->renderNode($source, $contexts);

      if (!empty($rendered)) {
        $build[$index] = $rendered;
      }
    }

    return $build;
  }

  /**
   * Recursively renders a node and its children.
   *
   * @param array $data
   *   The node data.
   * @param array $contexts
   *   The context objects.
   *
   * @return array
   *   A renderable array.
   */
  protected function renderNode(array $data, array $contexts): array {
    // Validate required data structure.
    if (empty($data) || !isset($data['source_id'])) {
      return [];
    }

    /** @var \Drupal\ui_patterns\Element\ComponentElementBuilder $builder */
    $builder = \Drupal::service('ui_patterns.component_element_builder'); // @phpcs:ignore

    try {
      // buildSource signature:
      // (array $element, string $slot_id, array $contexts, array $source,
      // array $settings). $settings should contain the contexts configuration.
      $build = $builder->buildSource([], 'content', [], $data, $contexts) ?? [];

      return $build['#slots']['content'][0] ?? [];
    }
    catch (\Throwable $th) {
      return [];
    }
  }

}
