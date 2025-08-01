<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Menu island plugin implementation.
 */
#[Island(
  id: 'menu',
  label: new TranslatableMarkup('Main actions'),
  description: new TranslatableMarkup('Provide copy/paste/duplicate actions.'),
  type: IslandType::Menu,
)]
class Menu extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    // Attribute data-contextual-menu is important for the js mapping.
    // @see assets/js/contextual_menu.js
    // Urls are generated with placeholders to be replaced in the js.
    $duplicate = $this->buildMenuItem($this->t('Duplicate'), 'duplicate');
    $duplicate = $this->htmxEvents->onClickDuplicate($duplicate, $builder_id, '__instance_id__', '__parent_id__', '__slot_id__', '__slot_position__');

    $paste = $this->buildMenuItem($this->t('Paste'), 'paste');
    $paste = $this->htmxEvents->onClickPaste($paste, $builder_id, '__instance_id__', '__parent_id__', '__slot_id__', '__slot_position__');

    return [
      $this->buildMenuItem($this->t('Copy'), 'copy'),
      $paste,
      $duplicate,
    ];
  }

}
