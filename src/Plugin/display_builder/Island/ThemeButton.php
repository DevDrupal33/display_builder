<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Theme switch button island plugin implementation.
 */
#[Island(
  id: 'theme',
  label: new TranslatableMarkup('Theme'),
  description: new TranslatableMarkup('Pick a theme mode as light/dark/system for the display builder.'),
  type: IslandType::Button,
  region: 'end',
)]
class ThemeButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    // The tooltip belongs to the dropdown wrapping the trigger, not to the
    // button, so an icon-only trigger has nothing to fall back on for its
    // accessible name. Name it from the icon.
    $button = $this->buildButton('', 'theme', 'sun');
    $button['#props']['icon_label'] = $this->t('Theme');

    return [
      '#type' => 'component',
      '#component' => 'display_builder:theme_menu',
      '#attributes' => [
        'placement' => 'bottom-end',
        'data-theme-switch' => TRUE,
      ],
      '#slots' => [
        'button' => $button,
      ],
      '#props' => [
        'tooltip' => $this->t('Switch the display builder interface theme.'),
      ],
    ];
  }

}
