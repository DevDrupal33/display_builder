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

    $remove = $this->buildMenuItem($this->t('Remove'), 'remove');
    // Also reachable with the Delete key, without opening the menu, acting on
    // whichever node is currently selected.
    // @see components/display_builder/js/keyboard.js
    $remove['#attributes']['data-keyboard-key'] = 'Delete';
    $remove['#attributes']['data-keyboard-help'] = (string) $this->t('Remove the selected element');
    $remove['#attributes']['aria-keyshortcuts'] = 'Delete';
    $remove = $this->htmxEvents->onClickDelete($remove, $builder_id);

    return [
      $this->buildMenuDivider(),
      $remove,
    ];
  }

}
