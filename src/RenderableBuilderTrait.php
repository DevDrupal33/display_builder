<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Trait with helpers to build renderables.
 */
trait RenderableBuilderTrait {

  /**
   * Build error message.
   *
   * @param string $builder_id
   *   The builder id.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to display.
   * @param bool $global
   *   (Optional) Try to use the default builder toast stack.
   * @param int|null $duration
   *   (Optional) Alert duration before closing.
   *
   * @return array
   *   The input render array.
   */
  public function buildError(string $builder_id, string|TranslatableMarkup $message, bool $global = FALSE, ?int $duration = NULL): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:alert',
      '#slots' => [
        'content' => $message,
      ],
      '#props' => [
        'variant' => 'danger',
        'icon' => 'exclamation-octagon',
        'open' => TRUE,
        'closable' => TRUE,
      ],
      '#attributes' => [
        'class' => 'db-message',
      ],
    ];

    if ($duration) {
      $build['#props']['duration'] = $duration;
    }

    if ($global) {
      // Append into the builder's toast stack rather than replacing whatever
      // is already on screen: a "beforeend" out-of-band swap keeps previous
      // messages visible, so a burst of errors stacks instead of each one
      // silently overwriting the last. htmx swaps the *content* of the
      // out-of-band element for any non-inline swap style, hence the wrapper.
      $build = [
        '#type' => 'container',
        'message' => $build,
      ];
      (new Htmx())
        ->swapOob(\sprintf('beforeend:#message-%s', $builder_id))
        ->applyTo($build);
    }

    return $build;
  }

  /**
   * Checks whether a renderable array is empty, or throws once rendered.
   *
   * Some render arrays (e.g. a comment field formatter's "Add comment" form
   * #lazy_builder placeholder, built against an unsaved sample entity with
   * no real ID - @see \Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityView)
   * throw rather than produce empty markup when actually rendered. Checking
   * with renderInIsolation() here, synchronously, keeps a bad lazy_builder
   * from surviving unresolved into the *caller's own* render array, where -
   * left unrendered - it would only fail later during Drupal core's own
   * BigPipe processing, well outside of this try/catch's reach, breaking
   * page rendering entirely instead of degrading gracefully to a
   * placeholder.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param array $renderable
   *   The renderable array to check.
   *
   * @return bool
   *   TRUE if the renderable is empty or fails to render, FALSE otherwise.
   */
  protected function isRenderEmptyOrFailing(RendererInterface $renderer, array $renderable): bool {
    try {
      $html = $renderer->renderInIsolation($renderable);
    }
    catch (\Throwable $e) {
      // "Rendered nothing" and "blew up" both leave here as TRUE, so this is
      // the only trace the failure ever leaves. Log it in the catch branch
      // only: the method runs for every node the Canvas draws.
      \Drupal::logger('display_builder')->warning(
        'Render failed, node treated as empty: @class: @message',
        ['@class' => \get_class($e), '@message' => $e->getMessage()]
      );

      return TRUE;
    }

    return empty(\trim((string) $html));
  }

  /**
   * Whether a node showed nothing, and so needs a placeholder or dropping.
   *
   * Canvas and Preview draw the same display and must agree on what is empty,
   * or the two disagree about a node the user can see in one and not the
   * other. They only part on what they do about it: the Canvas keeps the node
   * selectable behind a placeholder, the Preview shows the nothing the visitor
   * would get.
   *
   * A source that can never resolve here is not this question: it has already
   * answered for itself and does not render empty.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param array $data
   *   The UI Patterns form state data.
   * @param array $build
   *   What the source rendered, which is what decides emptiness.
   *
   * @return bool
   *   TRUE when the node has nothing to show.
   *
   * @see \Drupal\display_builder\Plugin\UiPatterns\Source\BlockSource
   */
  protected function needsPlaceholder(RendererInterface $renderer, array $data, array $build): bool {
    // A token resolving to nothing keeps the wrapper renderSource() gave it,
    // so the renderer sees markup where the user sees an empty box.
    if (($data['source_id'] ?? NULL) === 'token' && isset($build['content']) && empty($build['content'])) {
      return TRUE;
    }

    return $this->isRenderEmptyOrFailing($renderer, $build);
  }

  /**
   * Build placeholder component wrapper.
   *
   * @param string $label
   *   The placeholder label.
   * @param string $title
   *   (Optional) Title attribute value.
   * @param array $vals
   *   (Optional) HTMX vals data when placeholder trigger something when moving.
   * @param string|null $keywords
   *   (Optional) Data attributes keywords for search.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholder(string|TranslatableMarkup $label, string $title = '', array $vals = [], ?string $keywords = NULL): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:placeholder',
      '#slots' => [
        'content' => $label,
      ],
    ];

    if (isset($vals['source_id'])) {
      $build['#attributes']['class'][] = \sprintf('db-placeholder-%s', $vals['source_id']);
    }

    $testid_source = $vals['source']['component']['component_id']
      ?? $vals['source']['plugin_id']
      ?? $vals['source']['derivable_context']
      ?? NULL;

    if ($testid_source !== NULL) {
      $build['#attributes']['data-testid'] = \sprintf('placeholder-%s', \str_replace(':', '-', $testid_source));
    }
    elseif (isset($vals['source_id'])) {
      $build['#attributes']['data-testid'] = \sprintf('placeholder-%s', $vals['source_id']);
    }
    elseif (\is_string(\reset($vals))) {
      $build['#attributes']['data-testid'] = \sprintf('placeholder-%s', \reset($vals));
    }

    if ($keywords) {
      $build['#attributes']['data-keywords'] = \trim(\strtolower($keywords));
    }

    if (!empty($title)) {
      $build['#attributes']['title'] = $title;
    }

    if (!empty($vals)) {
      (new Htmx())->vals($vals)->applyTo($build);
    }

    return $build;
  }

  /**
   * Build placeholder as Button.
   *
   * @param string $label
   *   The placeholder label.
   * @param array $vals
   *   (Optional) HTMX vals data when placeholder trigger something when moving.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderButton(string|TranslatableMarkup $label, array $vals = []): array {
    $build = $this->buildPlaceholder($label, '', $vals);
    $build['#props']['variant'] = 'button';
    // To be able to identify the node when dragging and set the drawer title.
    $build['#attributes']['data-node-title'] = (string) $label;

    return $build;
  }

  /**
   * Build placeholder as List.
   *
   * @param string $label
   *   The placeholder label.
   * @param array $vals
   *   (Optional) HTMX vals data when placeholder trigger something when moving.
   * @param string|null $keywords
   *   (Optional) Keywords attributes to add used by search.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderList(string|TranslatableMarkup $label, array $vals = [], ?string $keywords = NULL): array {
    $build = $this->buildPlaceholder($label, '', $vals);
    $build['#props']['variant'] = 'list';
    // To be able to identify the node when dragging and set the drawer title.
    $build['#attributes']['data-node-title'] = (string) $label;

    if ($keywords) {
      $build['#attributes']['data-keywords'] = \trim(\strtolower($keywords));
    }

    return $build;
  }

  /**
   * Build placeholder standing for a whole page region.
   *
   * A region is not a control. It stands for an area another level of the page
   * fills, so it renders as a hatched box carrying a name and a sentence
   * saying what fills it, instead of the one-line chip a control gets. Same
   * shape the Preview island gives the same slots, so the two agree on sight
   * as well as in words.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The region name: its job, never a plugin id.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $help
   *   One sentence saying what fills it, and where.
   * @param string $size
   *   (Optional) 'md' for a strip, 'lg' for a page's whole content area.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderRegion(string|TranslatableMarkup $label, string|TranslatableMarkup $help, string $size = 'md'): array {
    $build = $this->buildPlaceholder($label);
    $build['#props']['variant'] = 'region';
    $build['#attributes']['class'][] = 'db-placeholder-region--' . $size;
    // To be able to identify the node when dragging and set the drawer title.
    $build['#attributes']['data-node-title'] = (string) $label;
    $build['#slots']['content'] = [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label,
        '#attributes' => ['class' => ['db-placeholder__region-title']],
      ],
      'help' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $help,
        '#attributes' => ['class' => ['db-placeholder__region-help']],
      ],
    ];

    return $build;
  }

  /**
   * Placeholder standing for a node that rendered nothing.
   *
   * The other half of ::buildPlaceholderRegion()'s two sentences, and the
   * reason they share a shape: from the outside both are an area with no
   * markup in it, and the only thing the user needs to tell apart is whether
   * the page will fill it or they have to.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   What the node is, so it stays recognizable while showing nothing.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $help
   *   (Optional) Why it can render empty, when the default sentence would be
   *   misleading for this source.
   *
   * @see \Drupal\display_builder\EmptyPlaceholderHelpInterface
   *
   * @return array
   *   A renderable array.
   */
  protected function buildEmptyPlaceholder(string|TranslatableMarkup $label, string|TranslatableMarkup|null $help = NULL): array {
    return $this->buildPlaceholderRegion(
      $label,
      $help ?? new TranslatableMarkup('Empty. Configure it to make it visible.'),
    );
  }

  /**
   * Build placeholder.
   *
   * @param string $builder_id
   *   The builder id.
   * @param string $label
   *   The placeholder label.
   * @param array $vals
   *   HTMX vals data if the placeholder is triggering something when moving.
   * @param \Drupal\Core\Url $preview_url
   *   The preview_url prop value.
   * @param string|null $keywords
   *   (Optional) Keywords attributes to add used by search.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderListWithPreview(string $builder_id, string|TranslatableMarkup $label, array $vals, Url $preview_url, ?string $keywords = NULL): array {
    $build = $this->buildPlaceholderList($label, $vals, $keywords);

    // Do not include entity field previews as we don't have generated value.
    if (isset($vals['source_id']) && ($vals['source_id'] === 'entity_field' || $vals['source_id'] === 'entity_reference')) {
      return $build;
    }

    $this->applyPreview($build, $builder_id, $preview_url);

    return $build;
  }

  /**
   * Build placeholder.
   *
   * @param string $label
   *   The placeholder label.
   * @param array $vals
   *   HTMX vals data if the placeholder is triggering something when moving.
   * @param string|null $keywords
   *   (Optional) Keywords attributes to add used by search.
   * @param string|null $thumbnail
   *   (Optional) The thumbnail URL.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderCard(string|TranslatableMarkup $label, array $vals, ?string $keywords = NULL, ?string $thumbnail = NULL): array {
    $build = $this->buildPlaceholder($label, '', $vals);

    if ($thumbnail) {
      $build['#slots']['image'] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          // @todo generate proper relative url.
          'src' => '/' . $thumbnail,
        ],
      ];
    }

    if ($keywords) {
      $build['#attributes']['data-keywords'] = \trim(\strtolower($keywords));
    }

    return $build;
  }

  /**
   * Build placeholder.
   *
   * @param string $builder_id
   *   The builder id.
   * @param string $label
   *   The placeholder label.
   * @param array $vals
   *   HTMX vals data if the placeholder is triggering something when moving.
   * @param \Drupal\Core\Url $preview_url
   *   The preview_url prop value.
   * @param string|null $keywords
   *   (Optional) Keywords attributes to add used by search.
   * @param string|null $thumbnail
   *   (Optional) The thumbnail URL.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderCardWithPreview(string $builder_id, string|TranslatableMarkup $label, array $vals, Url $preview_url, ?string $keywords = NULL, ?string $thumbnail = NULL): array {
    $build = $this->buildPlaceholderCard($label, $vals, $keywords, $thumbnail);
    $this->applyPreview($build, $builder_id, $preview_url);

    return $build;
  }

  /**
   * Build a button.
   *
   * Uniq id is required for keyboard mapping with ajax requests.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The button label.
   * @param string $action
   *   (Optional) The action value attribute. Used mainly for e2e tests.
   * @param string|null $icon
   *   (Optional) The icon name. Default none.
   * @param string|TranslatableMarkup|null $tooltip
   *   (Optional) Enable the tooltip feature. Default no tooltip.
   * @param array|null $keyboard
   *   (Optional) Keyboard shortcut as associative array key => description.
   *
   * @return array
   *   The button render array.
   */
  protected function buildButton(
    string|TranslatableMarkup $label,
    ?string $action,
    ?string $icon = NULL,
    string|TranslatableMarkup|null $tooltip = NULL,
    ?array $keyboard = NULL,
  ): array {
    $button = [
      '#type' => 'component',
      '#component' => 'display_builder:button',
      '#props' => [
        'label' => $label,
        'icon' => $icon,
        'tooltip' => $tooltip,
      ],
    ];

    if ($keyboard) {
      $button['#attributes']['data-keyboard-key'] = \key($keyboard);
      $button['#attributes']['aria-keyshortcuts'] = $button['#attributes']['data-keyboard-key'];
      $button['#attributes']['data-keyboard-help'] = \reset($keyboard) ?? '';
    }

    // Used to ease e2e tests.
    if ($action) {
      $button['#attributes']['data-island-action'] = $action;
      $button['#attributes']['data-testid'] = $action;
    }

    return $button;
  }

  /**
   * Build a menu item.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The menu title.
   * @param string $value
   *   The menu value.
   * @param string|null $icon
   *   (Optional) The icon name. Default none.
   * @param string $icon_position
   *   (Optional) The icon position. Default 'prefix'.
   * @param bool $disabled
   *   (Optional) Is the menu disabled? Default no.
   * @param array $submenu
   *   (Optional) Nested menu item render arrays, e.g. built with this same
   *   method, to display as a submenu. Default none.
   *
   * @return array
   *   The menu item render array.
   */
  protected function buildMenuItem(
    string|TranslatableMarkup $title,
    string $value,
    ?string $icon = NULL,
    string $icon_position = 'prefix',
    bool $disabled = FALSE,
    array $submenu = [],
  ): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:menu_item',
      '#props' => [
        'title' => $title,
        'value' => $value,
        'icon' => $icon,
        'icon_position' => $icon_position,
        'disabled' => $disabled,
      ],
      '#attributes' => [
        // Attribute data-contextual-menu is important for the js mapping.
        // @see components/contextual_menu/contextual_menu.js
        'data-contextual-menu' => TRUE,
      ],
    ];

    if ($submenu) {
      $build['#slots']['submenu'] = $submenu;
    }

    return $build;
  }

  /**
   * Build a menu item divider.
   *
   * @return array
   *   The menu item render array.
   */
  protected function buildMenuDivider(): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:menu_item',
      '#props' => [
        'variant' => 'divider',
      ],
    ];
  }

  /**
   * Build draggables placeholders.
   *
   * Used in library islands.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param array $draggables
   *   Draggable placeholders.
   * @param string $variant
   *   (Optional) The variant.
   *
   * @return array
   *   The draggables render array.
   */
  protected function buildDraggables(string $builder_id, array $draggables, string $variant = ''): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:draggables',
      '#slots' => [
        'content' => $draggables,
      ],
      '#attributes' => [
        // Required for JavaScript @see components/draggables/draggables.js.
        'data-db-id' => $builder_id,
      ],
    ];

    if ($variant) {
      $build['#props']['variant'] = $variant;
    }

    return $build;
  }

  /**
   * Build tabs.
   *
   * @param string $id
   *   The ID. Used for saving active tab in local storage.
   * @param array $tabs
   *   Tabs as links.
   * @param bool $contextual
   *   (Optional) Is the tabs contextual? Default no.
   *
   * @return array
   *   The tabs render array.
   */
  protected function buildTabs(string $id, array $tabs, bool $contextual = FALSE): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:tabs',
      '#props' => [
        'tabs' => $tabs,
        'contextual' => $contextual,
      ],
    ];

    if ($id) {
      $build['#props']['id'] = $id;
    }

    return $build;
  }

  /**
   * Build input.
   *
   * @param string $id
   *   The ID. Used for saving active tab in local storage.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param string $type
   *   The input type.
   * @param string $size
   *   (Optional) The input size. Default medium.
   * @param string|null $autocomplete
   *   (Optional) The input autocomplete.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $placeholder
   *   (Optional) The input placeholder.
   * @param bool|null $clearable
   *   (Optional) The input clearable.
   * @param string|null $icon
   *   (Optional) The input icon.
   *
   * @return array
   *   The input render array.
   */
  protected function buildInput(string $id, string|TranslatableMarkup $label, string $type, string $size = 'medium', ?string $autocomplete = NULL, string|TranslatableMarkup $placeholder = '', ?bool $clearable = NULL, ?string $icon = NULL): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:input',
      '#props' => [
        'label' => $label,
        'variant' => $type,
        'size' => $size,
      ],
    ];

    if ($id) {
      $build['#props']['id'] = $id;
    }

    if ($autocomplete) {
      $build['#props']['autocomplete'] = $autocomplete;
    }

    if ($placeholder) {
      $build['#props']['placeholder'] = $placeholder;
    }

    if ($clearable) {
      $build['#props']['clearable'] = TRUE;
    }

    if ($icon) {
      $build['#props']['icon'] = $icon;
    }

    return $build;
  }

  /**
   * Wraps a renderable in a div.
   *
   * Commonly used with tabs.
   *
   * @param array $content
   *   The renderable content.
   * @param string $id
   *   (Optional) The div id.
   *
   * @return array
   *   The wrapped render array.
   */
  protected function wrapContent(array $content, string $id = ''): array {
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      'content' => $content,
    ];

    if (!empty($id)) {
      $build['#attributes']['id'] = $id;
    }

    return $build;
  }

  /**
   * Make a placeholder show a preview popup on hover.
   *
   * Only the request is declared here. Showing, positioning and hiding the
   * popup belongs to js/preview.js, which can act once the response is in
   * the DOM - htmx alone can only show an empty box the moment the pointer
   * arrives, which Floating UI then measures at the wrong size.
   *
   * @param array $build
   *   The placeholder renderable, altered by reference.
   * @param string $builder_id
   *   The builder id, owning the popup element.
   * @param \Drupal\Core\Url $preview_url
   *   The URL returning the preview markup.
   */
  private function applyPreview(array &$build, string $builder_id, Url $preview_url): void {
    // Marks the element for js/preview.js, whatever the placeholder variant.
    // The URL itself lives in hx-get, JS only needs to recognize a trigger.
    $build['#attributes']['data-preview'] = TRUE;

    (new Htmx())
      ->get($preview_url)
      ->target(\sprintf('#preview-%s', $builder_id))
      // Hover intent. js/preview.js cancels the request if the pointer left
      // in the meantime, as htmx debounces but never cancels on its own.
      // Placeholders are focusable, so keyboard users reach the preview the
      // same way - focusin, because focus does not bubble to the delegated
      // listener that arbitrates which trigger is the current one.
      ->trigger('mouseenter delay:250ms, focusin delay:250ms')
      ->applyTo($build);
  }

}
