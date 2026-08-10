<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Menu island plugin implementation.
 *
 * Adds copy/paste/merge/delete actions for the ui_styles third-party
 * setting a node carries (@see StylesPanel), independent of copying the
 * node itself.
 */
#[Island(
  id: 'menu_styles',
  label: new TranslatableMarkup('Styles menu'),
  description: new TranslatableMarkup('Copy, paste, merge and delete styles.'),
  type: IslandType::Menu,
  modules: ['ui_styles'],
)]
class MenuStyles extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    // Names the copied element once, at the top, so Paste and Merge below
    // stay plain verbs instead of each repeating where the styles came from.
    // Carries no text of its own: contextual_menu.js writes the name in and
    // reveals it, since only the client knows what is on the clipboard.
    $source = [
      '#type' => 'component',
      '#component' => 'display_builder:menu_item',
      '#props' => [
        'variant' => 'label',
      ],
      '#attributes' => [
        'class' => ['db-menu__styles-source'],
        'hidden' => TRUE,
      ],
    ];

    $copy = $this->buildMenuItem($this->t('Copy'), 'copy_styles');

    $paste = $this->buildMenuItem($this->t('Paste'), 'paste_styles');
    $paste = $this->htmxEvents->onClickPasteStyles($paste, $builder_id);

    $merge = $this->buildMenuItem($this->t('Merge'), 'merge_styles');
    $merge = $this->htmxEvents->onClickPasteStyles($merge, $builder_id);

    // "Clear", not "Delete": it empties this element's styles, and sits one
    // menu away from "Remove", which deletes the element itself. The value
    // stays delete_styles - it is what binds the item to its route.
    $clear = $this->buildMenuItem($this->t('Clear'), 'delete_styles');
    $clear = $this->htmxEvents->onClickDeleteStyles($clear, $builder_id);

    // Empties the styles clipboard. Purely client-side, hence no htmx event:
    // the clipboard is a localStorage entry (@see contextual_menu.js). Shown
    // only once there is something to forget, like the label above.
    $forget = $this->buildMenuItem($this->t('Forget copied styles'), 'forget_styles');
    $forget['#attributes']['class'][] = 'db-menu__styles-forget';
    $forget['#attributes']['hidden'] = TRUE;

    $styles = $this->buildMenuItem($this->t('Styles'), '', submenu: [$source, $copy, $paste, $merge, $clear, $forget]);

    return [
      $this->buildMenuDivider(),
      $styles,
    ];
  }

}
