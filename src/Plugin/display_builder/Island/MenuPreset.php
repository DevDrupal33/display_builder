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
  id: 'menu_preset',
  label: new TranslatableMarkup('Preset actions'),
  description: new TranslatableMarkup('Provide preset actions.'),
  type: IslandType::Menu,
)]
class MenuPreset extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    // Attribute data-contextual-menu is important for the js mapping.
    // @see assets/js/contextual_menu.js
    // Urls are generated with placeholders to be replaced in the js.
    $save_preset = $this->buildMenuItem($this->t('Save as preset'), 'save_preset');
    $save_preset = $this->htmxEvents->onClickSavePreset($save_preset, $builder_id, '__instance_id__', $this->t('Name of preset'));

    return [
      $this->buildMenuDivider(),
      $save_preset,
    ];
  }

}
