<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Theme mode button island plugin implementation.
 */
#[Island(
  id: 'theme_mode',
  label: new TranslatableMarkup('Theme mode selector'),
  description: new TranslatableMarkup('Pick a theme mode as light/dark/system for the display builder.'),
  type: IslandType::Button,
)]
class ThemeModeButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:theme_menu',
      '#attributes' => [
        'placement' => 'bottom-end',
        'class' => ['db-theme-switcher'],
      ],
      '#slots' => [
        'button' => $this->buildButton('', $this->t('Switch the display builder theme.'), '\\', FALSE, 'sun'),
      ],
      '#props' => [
        'title' => $this->t('Switch the display builder theme.'),
        'icon' => 'sun',
        'keyboard' => '\\',
      ],
    ];
  }

}
