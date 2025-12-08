<?php

declare(strict_types=1);

namespace Drupal\display_builder\Render;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\ActiveTheme;
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
    private RendererInterface $renderer,
    private ThemeManagerInterface $themeManager,
    private ThemeInitializationInterface $themeInitialization,
    private ConfigFactoryInterface $configFactory,
    private AssetCollectionRendererInterface $cssCollectionRenderer,
    private AssetCollectionRendererInterface $jsCollectionRenderer,
    private AssetResolverInterface $assetResolver,
    private LanguageManagerInterface $languageManager,
    private StateInterface $state,
  ) {}

  /**
   * Renders the content in a declarative shadow dom.
   *
   * @param array $content
   *   The render array representing the content inside the shadow tree.
   * @param array $slotted
   *   The render array of slotted content (outside the shadow tree).
   * @param bool $admin
   *   True to render with admin theme, False to render with front theme.
   * @param bool $htmx
   *   HTMX is removing templates with shadowroot before swap. So we need to
   *   print a 'normal' template and add the shadowroot with javascript.
   *   We hope it is a temporary situation.
   *   See display_builder/declarative_shadow_dom asset library.
   *
   * @return array
   *   The rendered content.
   */
  public function render(array $content, array $slotted, bool $admin = FALSE, $htmx = FALSE): array {
    // Set the theme to render with.
    $current_theme = $this->themeManager->getActiveTheme();
    $theme_name = $this->configFactory->get('system.theme')->get($admin ? 'admin' : 'default');
    $theme = $this->themeInitialization->getActiveThemeByName($theme_name);
    $this->themeManager->setActiveTheme($theme);
    $content = $this->addThemeLibraries($content, $theme, $admin);

    // Render, replacing the placeholders because this will not be part of the
    // main render pipeline. To replace placeholders now, we use
    // RendererInterface::renderRoot() instead of RendererInterface::render().
    $render_context = new RenderContext();
    $content = $this->renderer->executeInRenderContext($render_context, function () use ($content, $slotted, $htmx) {
      // We don't care about the return value (which is just $html['#markup']),
      // but about the resulting render array.
      // @todo Simplify this when https://www.drupal.org/node/2495001 lands.
      $this->renderer->renderInIsolation($content);
      $content = $this->wrapInShadowDom($content, $slotted, $htmx);

      return $content;
    });

    // Restore default theme after rendering.
    $this->themeManager->setActiveTheme($current_theme);

    return $content;
  }

  /**
   * Wrap rendered in Declarative Shadow DOM.
   *
   * @param array $content
   *   The render array representing the rendered content.
   * @param array $slotted
   *   The render array of slotted content (outside the shadow tree).
   * @param bool $htmx
   *   HTMX is removing templates with shadowroot before swap. So we need to
   *   print a 'normal' template and add the shadowroot with javascript.
   *   We hope it is a temporary situation.
   *   See display_builder/declarative_shadow_dom asset library.
   *
   * @return array
   *   The content isolated in a web component with shadow root
   */
  protected function wrapInShadowDom(array $content, array $slotted, bool $htmx = FALSE): array {
    $assets = AttachedAssets::createFromRenderArray($content);
    $libraries = $this->processAssetLibraries($assets);

    return [
      '#type' => 'inline_template',
      '#template' => <<<'HTML'
      <db-isolate>
        <template {% if shadow %}shadowrootmode="open"{% endif %}>
          <style> div { color: red !important;  }</style>
          {{ libraries }}
          {{ shadow_content }}
          <slot></slot>
        </template>
        {{ slotted }}
      </db-isolate>
HTML,
      '#context' => [
        'shadow_content' => $content,
        'slotted' => $slotted,
        // Shadow DOM can include <style> or <link rel="stylesheet">.
        // Those 'local' styles can affect:
        // - shadow tree (what is inside the <template> element)
        // - shadow host with :host and :host() pseudoclasses
        // - slotted elements (coming from light DOM), ::slotted(selector)
        //   allows to select slotted elements themselves, but not their
        //   children.
        // Document styles can affect:
        // - shadow host (as it lives in the outer document)
        // - slotted elements and their contents (as that’s also in the
        //   outer document)
        'libraries' => $libraries,
        'shadow' => !$htmx,
      ],
    ];
  }

  /**
   * Add theme's assets libraries.
   *
   * @param array $content
   *   The content to attach to.
   * @param \Drupal\Core\Theme\ActiveTheme $theme
   *   Active theme.
   * @param bool $admin
   *   Is the theme an admin theme?
   *
   * @return array
   *   The content with attached libraries.
   */
  protected function addThemeLibraries(array $content, ActiveTheme $theme, bool $admin = FALSE): array {
    $content['#attached']['library'][] = 'system/base';

    if ($admin) {
      $content['#attached']['library'][] = 'system/admin';
    }

    // Attach libraries used by this theme.
    foreach ($theme->getLibraries() as $library) {
      $content['#attached']['library'][] = $library;
    }

    return $content;
  }

  /**
   * Processes asset libraries into render arrays.
   *
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
   *   The attached assets collection.
   *
   * @return array
   *   An array keyed by asset type, with keys:
   *     - styles
   *     - scripts
   *     - scripts_bottom
   */
  protected function processAssetLibraries(AttachedAssetsInterface $assets) {
    $variables = [];
    $maintenance_mode = \defined('MAINTENANCE_MODE') || $this->state->get('system.maintenance_mode');
    $current_language = $this->languageManager->getCurrentLanguage();

    // Optimize CSS if necessary, but only during normal site operation.
    $optimize_css = !$maintenance_mode && $this->configFactory->get('system.performance')->get('css.preprocess');

    $css_assets = $this->assetResolver->getCssAssets($assets, $optimize_css, $current_language);
    $variables['styles'] = $this->cssCollectionRenderer->render($css_assets);

    // Optimize JS if necessary, but only during normal site operation.
    $optimize_js = !$maintenance_mode && $this->configFactory->get('system.performance')->get('js.preprocess');

    [$js_assets_header, $js_assets_footer] = $this->assetResolver->getJsAssets($assets, $optimize_js, $current_language);
    $variables['scripts'] = $this->jsCollectionRenderer->render($js_assets_header);
    $variables['scripts_bottom'] = $this->jsCollectionRenderer->render($js_assets_footer);

    return $variables;
  }

}
