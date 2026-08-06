<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\DisplayBuilderHtmx;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview island plugin implementation.
 */
#[Island(
  id: 'preview',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Preview'),
  description: new TranslatableMarkup('Show a real time preview of the display.'),
  type: IslandType::Preview,
  icon: 'binoculars',
)]
class PreviewPanel extends IslandPluginBase {

  use IslandReloadEventsTrait;

  /**
   * The component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->componentElementBuilder = $container->get('ui_patterns.component_element_builder');
    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'p',
      'help' => t('Show the preview'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    // Rendered inside a preview iframe already (@see the live-preview and
    // isolated-preview routes): emit the real content, with no further
    // wrapping, so it is not iframes all the way down.
    if (!empty($options['in_iframe'])) {
      return $this->renderPreviewSources($data);
    }

    // Every buildable previews through the same isolated route, whatever page
    // it would otherwise live in. An iframe has its own viewport, so the
    // display's media queries reflow with the viewport switcher's width, which
    // an in-page div can never do. The route renders this same island with the
    // in_iframe option above, on a bare full page with no site or admin chrome.
    // @see \Drupal\display_builder\Controller\ApiPreviewController::getDisplayPreview()
    // @see \Drupal\display_builder\Hook\LivePreviewChrome
    $preview_url = Url::fromRoute('display_builder.preview_island', [
      'display_builder_instance' => $builder->id(),
    ]);

    // A persistent iframe plus a hidden refresh token. On every change the
    // token's content is bumped out of band (@see reloadWithGlobalData())
    // rather than the iframe being rebuilt - replacing the iframe reloads it
    // from blank and flashes white each edit. js/live_preview.js watches the
    // token and double-buffers the fresh render over the current one, so the
    // visible preview never blanks.
    // @see components/display_builder/js/live_preview.js
    return [
      // Two nested boxes wrap the iframe:
      // - the frame owns the available space in the pane (fills below header)
      //   and is the horizontal scroll owner;
      // - the scale box takes the render's on-screen (scaled) size and centres,
      //   so a zoomed render has no empty side zones and scrolls only when it
      //   is genuinely wider than the pane.
      // The iframe keeps its true (unscaled) viewport width - real media-query
      // reflow - and is scaled down onto the scale box.
      // @see js/live_preview.js, which loads its double-buffer iframe into the
      // scale box, and the .db-live-preview* rules in display_builder.css.
      'frame' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['db-live-preview-frame']],
        'scale' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['db-live-preview-scale']],
          'iframe' => $this->buildIframe($preview_url),
        ],
      ],
      'refresh' => $this->buildRefreshToken($builder),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * The preview is an iframe: rebuilding the whole island would replace it and
   * reload it from blank, flashing white on each edit. Swap only the hidden
   * refresh token's content instead; js/live_preview.js sees the change and
   * double-buffers a fresh render over the current one. The deferred-reveal
   * path (@see ApiController::reloadIsland()) flows through here too, so a
   * stale pane coming back on screen refreshes flash-free as well.
   *
   * @see components/display_builder/js/live_preview.js
   */
  protected function reloadWithGlobalData(InstanceInterface $instance): array {
    return DisplayBuilderHtmx::outOfBand(
      ['#markup' => (string) $instance->getHash()],
      '#' . $this->getRefreshTokenId($instance),
      'innerHTML'
    );
  }

  /**
   * Build the hidden token whose content changes on every refresh.
   *
   * The js/live_preview.js script observes this element; reloadWithGlobalData()
   * swaps its content (the instance's current state hash) via an out-of-band
   * swap on each change, which the observer turns into a flash-free refresh.
   * The element itself persists across those swaps, so its observer keeps
   * working for the pane's lifetime.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return array
   *   The token render array.
   *
   * @see components/display_builder/js/live_preview.js
   */
  private function buildRefreshToken(InstanceInterface $builder): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => (string) $builder->getHash(),
      '#attributes' => [
        'id' => $this->getRefreshTokenId($builder),
        'class' => ['db-live-preview-refresh'],
        'hidden' => 'hidden',
      ],
    ];
  }

  /**
   * The DOM id of the hidden refresh token, distinct from the pane's own id.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   *
   * @return string
   *   The token element id.
   */
  private function getRefreshTokenId(InstanceInterface $builder): string {
    return $this->getHtmlId((string) $builder->id()) . '-refresh';
  }

  /**
   * Render the preview sources in-page (as shown inside a preview iframe).
   *
   * @param array $data
   *   The instance sources to preview.
   *
   * @return array
   *   A list of render arrays, one per root source.
   */
  private function renderPreviewSources(array $data): array {
    if (empty($data)) {
      return [];
    }

    $placeholders = $this->alterPreviewPlaceholder($data);

    $returned = [];

    foreach ($data as $slot) {
      $build = $this->componentElementBuilder->buildSource([], 'content', [], $slot, $this->configuration['contexts'] ?? []);
      $build = $build['#slots']['content'][0] ?? [];

      // Some sources (e.g. a comment field's "Add comment" form) throw once
      // actually rendered rather than producing empty markup - @see
      // RenderableBuilderTrait::isRenderEmptyOrFailing(). Unlike
      // BuilderPanel, which already guards every source it builds the same
      // way, nothing here previously checked this, so a throwing source
      // shipped its #lazy_builder unresolved into the page, breaking Drupal
      // core's own BigPipe processing of unrelated placeholders (e.g. the
      // Navigation module's admin toolbar) later in the same request.
      if ($this->isRenderEmptyOrFailing($this->renderer, $build)) {
        $build = $this->buildPlaceholder($this->t('[Placeholder] No preview'));
      }

      $returned[] = $build;
    }

    if ($placeholders > 0) {
      // The markers are raw markup inside a token source, so they carry no
      // library of their own. Attach it here instead - and only here, because
      // this render is what the live-preview iframe loads, a document with none
      // of the builder's own CSS to inherit from.
      // @see assets/css/preview_placeholder.css
      $returned['#attached']['library'][] = 'display_builder/preview_placeholder';
    }

    return $returned;
  }

  /**
   * Build the preview iframe pointing at a real render URL.
   *
   * The iframe fills its pane (width: 100%), so the width the viewport switcher
   * sets on the pane becomes the iframe's own viewport width, reflowing the
   * page inside it for real.
   *
   * @param \Drupal\Core\Url $preview_url
   *   The render URL: a real page for buildables that have one, otherwise the
   *   isolated-preview route.
   *
   * @return array
   *   A render array for the iframe.
   */
  private function buildIframe(Url $preview_url): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $preview_url->toString(),
        'class' => ['db-live-preview'],
        'title' => $this->t('Live preview'),
        'loading' => 'lazy',
      ],
      // The draft render behind this URL is user- and edit-specific and must
      // always be current; never let the pane markup itself be cached.
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Replace placeholder for preview.
   *
   * Some block source will not be created, create a simple placeholder to have
   * a preview instead of nothing.
   *
   * @todo move as an issue to UI Patterns?
   *
   * @param array $data
   *   The instance data to replace.
   *
   * @return int
   *   How many sources were replaced by a marker.
   */
  private function alterPreviewPlaceholder(array &$data): int {
    $replacements = [
      [
        'search' => ['plugin_id' => 'local_tasks_block'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Local tasks (Tabs)'),
      ],
      [
        'search' => ['plugin_id' => 'system_messages_block'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Block messages'),
      ],
      [
        'search' => ['source_id' => 'page_title'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Page title'),
      ],
      [
        'search' => ['source_id' => 'main_page_content'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Page content'),
        'new_value_class' => 'db-preview-placeholder-lg',
      ],
      [
        'search' => ['source_id' => 'view_attachment_before'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View attachment before'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_exposed'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View exposed form'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_header'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View header'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_rows'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View rows'),
        'new_value_class' => 'db-preview-placeholder-lg',
      ],
      [
        'search' => ['source_id' => 'view_attachment_after'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View attachment after'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_pager'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View pager'),
      ],
      [
        'search' => ['source_id' => 'view_more'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View more'),
      ],
      [
        'search' => ['source_id' => 'view_footer'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View footer'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_feed_icons'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View feed icons'),
      ],
    ];

    return DisplayBuilderHelpers::findAndReplaceInArray($data, $replacements);
  }

}
