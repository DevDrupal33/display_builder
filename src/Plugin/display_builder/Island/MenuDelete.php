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
 */
#[Island(
  id: 'menu_delete',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Delete'),
  description: new TranslatableMarkup('Remove a component or block.'),
  type: IslandType::Menu,
)]
class MenuDelete extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    // Attribute data-contextual-menu is important for the js mapping.
    // @see assets/js/contextual_menu.js
    // Urls are generated with placeholders to be replaced in the js.
    $remove = $this->buildMenuItem($this->t('Remove'), 'remove');
    $remove = $this->htmxEvents->onClickDelete($remove, $builder_id, '__node_id__');

    return [
      $this->buildMenuDivider(),
      $remove,
    ];
  }

}
