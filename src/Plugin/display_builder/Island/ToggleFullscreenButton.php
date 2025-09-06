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
  id: 'toggle_fullscreen',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Toggle fullscreen'),
  description: new TranslatableMarkup('Toggle the builder as fullscreen.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'M' => new TranslatableMarkup('(shift + m) Toggle fullscreen'),
  ],
)]
class ToggleFullscreenButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:fullscreen_button',
      '#props' => [
        'icon' => 'fullscreen',
        'tooltip' => $this->t('Toggle fullscreen'),
        'keyboard' => 'M',
      ],
      // To ease e2e tests.
      '#attributes' => [
        'data-island-action' => 'fullscreen',
      ],
    ];
  }

}
