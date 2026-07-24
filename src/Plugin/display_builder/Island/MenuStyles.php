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

    $copy = $this->buildMenuItem($this->t('Copy'), 'copy_styles');

    $paste = $this->buildMenuItem($this->t('Paste'), 'paste_styles');
    $paste = $this->htmxEvents->onClickPasteStyles($paste, $builder_id);

    $merge = $this->buildMenuItem($this->t('Merge'), 'merge_styles');
    $merge = $this->htmxEvents->onClickPasteStyles($merge, $builder_id);

    $delete = $this->buildMenuItem($this->t('Delete'), 'delete_styles');
    $delete = $this->htmxEvents->onClickDeleteStyles($delete, $builder_id);

    $styles = $this->buildMenuItem($this->t('Styles'), '', submenu: [$copy, $paste, $merge, $delete]);

    return [
      $this->buildMenuDivider(),
      $styles,
    ];
  }

}
