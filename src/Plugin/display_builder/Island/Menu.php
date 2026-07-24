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
  id: 'menu',
  label: new TranslatableMarkup('Main menu items'),
  description: new TranslatableMarkup('Copy, paste and duplicate.'),
  type: IslandType::Menu,
)]
class Menu extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    // Copy, paste and duplicate are also reachable with Ctrl/Cmd+C / +V / +D,
    // without opening the menu, acting on whichever node is currently selected.
    // @see components/display_builder/js/keyboard.js
    $copy = $this->buildMenuItem($this->t('Copy'), 'copy');
    $this->addShortcut($copy, 'mod+c', (string) $this->t('Copy the selected element'));

    $paste = $this->buildMenuItem($this->t('Paste'), 'paste');
    $this->addShortcut($paste, 'mod+v', (string) $this->t('Paste next to the selected element'));
    $paste = $this->htmxEvents->onClickPaste($paste, $builder_id);

    $duplicate = $this->buildMenuItem($this->t('Duplicate'), 'duplicate');
    $this->addShortcut($duplicate, 'mod+d', (string) $this->t('Duplicate the selected element'));
    $duplicate = $this->htmxEvents->onClickDuplicate($duplicate, $builder_id);

    return [
      $copy,
      $paste,
      $duplicate,
    ];
  }

  /**
   * Adds a keyboard shortcut to a menu item render array.
   *
   * @param array $item
   *   The menu item render array, modified by reference.
   * @param string $key
   *   The canonical combo string, e.g. "mod+c".
   * @param string $help
   *   The help text shown in the shortcuts dialog.
   */
  private function addShortcut(array &$item, string $key, string $help): void {
    $item['#attributes']['data-keyboard-key'] = $key;
    $item['#attributes']['data-keyboard-help'] = $help;
    $item['#attributes']['aria-keyshortcuts'] = $key;
  }

}
