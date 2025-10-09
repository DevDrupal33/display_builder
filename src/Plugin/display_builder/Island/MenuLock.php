<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Menu island plugin implementation.
 */
#[Island(
  id: 'menu_lock',
  enabled_by_default: FALSE,
  label: new TranslatableMarkup('Lock and unlock'),
  description: new TranslatableMarkup('Lock and unlock parts of the display.'),
  type: IslandType::Menu,
)]
class MenuLock extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    // Attribute data-contextual-menu is important for the js mapping.
    // @see assets/js/contextual_menu.js
    // Urls are generated with placeholders to be replaced in the js.
    $item = $this->buildMenuItem($this->t('Lock'), 'lock');
    $item = $this->htmxEvents->onClickLock($item, $builder_id, '__node_id__');

    return [
      $this->buildMenuDivider(),
      $item,
    ];
  }

}
