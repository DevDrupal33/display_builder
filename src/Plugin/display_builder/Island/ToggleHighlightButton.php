<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Help button island plugin implementation.
 */
#[Island(
  id: 'toggle_highlight',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Toggle highlight'),
  description: new TranslatableMarkup('Toggle the builder highlight zones.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'H' => new TranslatableMarkup('(shift + h) Toggle highlight'),
  ],
)]
class ToggleHighlightButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:highlight_button',
      '#props' => [
        'icon' => 'border',
        'title' => $this->t('Highlight components, blocks and slots.'),
        'keyboard' => 'H',
      ],
    ];
  }

}
