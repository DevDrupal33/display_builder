<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Help button island plugin implementation.
 */
#[Island(
  id: 'keyboard_help',
  label: new TranslatableMarkup('Keyboard help'),
  description: new TranslatableMarkup('Information on the available keyboard shortcuts.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'h' => new TranslatableMarkup('Show this help for the keyboard mapping'),
  ],
)]
class KeyboardHelpButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $items = [];

    foreach ($data as $key => $label) {
      $items[] = [
        'title' => $this->t('<code>@key</code> @label', ['@key' => $key, '@label' => $label]),
      ];
    }

    $keyboard = $this->buildButton('', $this->t('Keyboard help'), 'h', FALSE, 'keyboard');
    $keyboard['#props']['variant'] = 'neutral';
    $keyboard['#attributes']['outline'] = TRUE;

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#slots' => [
        'button' => $keyboard,
        'content' => [
          '#type' => 'component',
          '#component' => 'display_builder:menu',
          '#slots' => [
            'label' => $this->t('Keyboard help'),
          ],
          '#props' => [
            'items' => $items,
          ],
          '#attributes' => [
            'class' => ['db-background'],
          ],
        ],
      ],
    ];
  }

}
