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
  id: 'menu_delete',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Delete action'),
  description: new TranslatableMarkup('Provide remove action.'),
  type: IslandType::Menu,
)]
class MenuDelete extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    // Attribute data-contextual-menu is important for the js mapping.
    // @see components/contextual_menu/contextual_menu.js
    // Urls are generated with placeholders to be replaced in the js.
    $remove = $this->buildMenuItem($this->t('Remove'), 'remove');
    $remove = $this->htmxEvents->onClickDelete($remove, $builder_id, '__instance_id__');

    $items = [
      $this->buildMenuDivider(),
      $remove,
    ];

    return $items;
  }

}
