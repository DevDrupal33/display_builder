<?php

declare(strict_types=1);

namespace Drupal\display_builder;

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
   *   (Optional) Try to use the default builder message placeholder.
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
      $build['#props']['id'] = \sprintf('message-%s', $builder_id);
      $build['#attributes']['hx-swap-oob'] = 'true';
    }

    return $build;
  }

  /**
   * Build placeholder.
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

    if ($keywords) {
      $build['#attributes']['data-keywords'] = \trim(\strtolower($keywords));
    }

    if (!empty($title)) {
      $build['#attributes']['title'] = $title;
    }

    if (!empty($vals)) {
      $build['#attributes']['hx-vals'] = \json_encode($vals);
    }

    return $build;
  }

  /**
   * Build placeholder.
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
  protected function buildPlaceholderButton(string|TranslatableMarkup $label, array $vals = [], ?string $keywords = NULL): array {
    $build = $this->buildPlaceholder($label, '', $vals);
    $build['#props']['variant'] = 'button';
    // To be able to identify the node when dragging and set the drawer title.
    $build['#attributes']['data-node-title'] = (string) $label;

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
   *
   * @return array
   *   A renderable array.
   */
  protected function buildPlaceholderButtonWithPreview(string $builder_id, string|TranslatableMarkup $label, array $vals, Url $preview_url, ?string $keywords = NULL): array {
    $build = $this->buildPlaceholderButton($label, $vals, $keywords);

    // Do not include entity field previews as we don't have generated value.
    if (isset($vals['source_id']) && ($vals['source_id'] === 'entity_field' || $vals['source_id'] === 'entity_reference')) {
      return $build;
    }

    $hide_script = \sprintf('Drupal.displayBuilder.hidePreview(%s)', $builder_id);
    $attributes = [
      'hx-get' => $preview_url->toString(),
      'hx-target' => \sprintf('#preview-%s', $builder_id),
      'hx-trigger' => 'mouseenter delay:250ms',
      'hx-on:mouseenter' => \sprintf('Drupal.displayBuilder.showPreview(%s, this)', $builder_id),
      'hx-on:focus' => \sprintf('Drupal.displayBuilder.showPreview(%s, this)', $builder_id),
      'hx-on:mouseleave' => $hide_script,
      'hx-on:blur' => $hide_script,
      // Disable the preview on click for a dragging operation.
      'hx-on:mousedown' => $hide_script,
    ];

    $build['#attributes'] = \array_merge($build['#attributes'], $attributes);

    return $build;
  }

  /**
   * Build placeholder.
   *
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
  protected function buildPlaceholderCardWithPreview(string|TranslatableMarkup $label, array $vals, Url $preview_url, ?string $keywords = NULL, ?string $thumbnail = NULL): array {
    $build = $this->buildPlaceholder($label, '', $vals);
    $build['#props']['preview_url'] = $preview_url;

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
  ): array {
    return [
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
        'data-contextual-menu' => TRUE,
      ],
    ];
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

}
