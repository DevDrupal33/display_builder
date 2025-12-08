<?php

declare(strict_types=1);

namespace Drupal\display_builder\Render;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Content renderer for declarative shadow dom.
 */
class DeclarativeShadowDomRenderer {

  /**
   * Constructs a new HtmxRenderer.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected RenderCacheInterface $renderCache,
    protected ThemeManagerInterface $themeManager,
    protected ThemeInitializationInterface $themeInitialization,
    protected ConfigFactoryInterface $configFactory,
    protected array $rendererConfig,
  ) {}

  /**
   * Renders the content in a declarative shadow dom.
   *
   * @param array $content
   *   The render array representing the content.
   * @param bool $admin
   *   True to render with admin theme, False to render with front theme.
   *
   * @return array
   *   The rendered content.
   */
  public function render(array $content, bool $admin = FALSE): array {
    $token = Crypt::randomBytesBase64(55);
    $html = [
      '#type' => 'inline_template',
      '#template' => <<<'HTML'
      <db-isolate>
        <template shadowrootmode="open">
          <css-placeholder token="{{ placeholder_token }}">
          <js-placeholder token="{{ placeholder_token }}">
          <!--js-bottom-placeholder token="{{ placeholder_token }}"-->
          <slot></slot>
        </template>
        {{ content }}
      </db-isolate>
HTML,
      '#context' => [
        'content' => [
          'messages' => ['#type' => 'status_messages'],
          'main_content' => $content,
        ],
        'placeholder_token' => $token,
      ],
    ];
    // Create placeholder strings for these keys.
    $types = [
      'styles' => 'css',
      'scripts' => 'js',
      // We are not rendering script_bottom placeholder, in order to not
      // trigger BigPipe. Is it a mistake?
    ];

    foreach ($types as $type => $placeholder_name) {
      $placeholder = '<' . $placeholder_name . '-placeholder token="' . $token . '">';
      $html['#attached']['html_response_attachment_placeholders'][$type] = $placeholder;
    }

    // Render, but don't replace placeholders yet, because that happens later in
    // the render pipeline. To not replace placeholders yet, we use
    // RendererInterface::render() instead of RendererInterface::renderRoot().
    // @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor.
    $render_context = new RenderContext();

    if ($admin) {
      // Render with the admin theme, loading the expected templates and
      // executing the expected hooks.
      $current_theme = $this->themeManager->getActiveTheme();
      $admin_theme = $this->configFactory->get('system.theme')->get('admin');
      $admin_theme = $this->themeInitialization->getActiveThemeByName($admin_theme);
      $this->themeManager->setActiveTheme($admin_theme);
    }
    $this->renderer->executeInRenderContext($render_context, function () use (&$html) {
      // RendererInterface::render() renders the $html render array and updates
      // it in place. We don't care about the return value (which is just
      // $html['#markup']), but about the resulting render array.
      // @todo Simplify this when https://www.drupal.org/node/2495001 lands.
      $this->renderer->render($html);
    });

    if ($admin) {
      // Restore default theme.
      $this->themeManager->setActiveTheme($current_theme);
    }

    // RendererInterface::render() always causes bubbleable metadata to be
    // stored in the render context, no need to check it conditionally.
    $bubbleable_metadata = $render_context->pop();
    $bubbleable_metadata->applyTo($html);
    $content = $this->renderCache->getCacheableRenderArray($html);

    // Also associate the required cache contexts.
    // (Because we use ::render() above and not ::renderRoot(), we manually must
    // ensure the HTML response varies by the required cache contexts.)
    $content['#cache']['contexts'] = Cache::mergeContexts($content['#cache']['contexts'], $this->rendererConfig['required_cache_contexts']);

    // Also associate the "rendered" cache tag. This allows us to invalidate the
    // entire render cache, regardless of the cache bin.
    $content['#cache']['tags'][] = 'rendered';

    $content = $this->addThemeLibraries($content, $admin);

    return $content;
  }

  /**
   * Add theme's assets libraries.
   *
   * @param array $content
   *   The content to attach to.
   * @param bool $admin
   *   True to load admin theme, False to load front theme.
   *
   * @return array
   *   The content with attached libraries.
   */
  protected function addThemeLibraries(array $content, bool $admin = FALSE): array {
    $content['#attached']['library'][] = 'system/base';
    $theme = $this->themeManager->getActiveTheme();

    if ($admin) {
      $content['#attached']['library'][] = 'system/admin';
      $theme_name = $this->configFactory->get('system.theme')->get('admin');
      $theme = $this->themeInitialization->getActiveThemeByName($theme_name);
    }

    // Attach libraries used by this theme.
    foreach ($theme->getLibraries() as $library) {
      $content['#attached']['library'][] = $library;
    }

    return $content;
  }

}
