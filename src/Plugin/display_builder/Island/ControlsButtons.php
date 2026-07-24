<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Controls button island plugin implementation.
 */
#[Island(
  id: 'controls',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Controls'),
  description: new TranslatableMarkup('Control the building experience.'),
  type: IslandType::Button,
  default_region: 'end',
)]
class ControlsButtons extends IslandPluginToolbarButtonConfigurationBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $buttons = [];
    $library = [];

    if ($this->isButtonEnabled('expand')) {
      $buttons[] = $this->buildExpandButton();
      $library[] = 'display_builder/expand';
    }

    if ($this->isButtonEnabled('theme')) {
      $buttons[] = $this->buildThemeMenu();
    }

    if ($this->isButtonEnabled('help')) {
      $buttons[] = $this->buildKeyboardButton();
    }

    if (empty($buttons)) {
      return [];
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => $buttons,
      ],
      '#attached' => [
        'library' => $library,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function hasButtons(): array {
    return [
      'expand' => [
        'title' => $this->t('Expand'),
        'description' => $this->t('Expand the builder to cover the current viewport.'),
        'default' => 'icon',
      ],
      'theme' => [
        'title' => $this->t('Theme'),
        'description' => $this->t('Pick a theme mode as light/dark/system for the display builder.'),
        'default' => 'icon',
      ],
      'help' => [
        'title' => $this->t('Help'),
        'description' => $this->t('Information about the available keyboard shortcuts.'),
        'default' => 'icon',
      ],
    ];
  }

  /**
   * Builds the expand button.
   *
   * @return array
   *   The expand button render array.
   */
  private function buildExpandButton(): array {
    $expand = $this->buildButton(
      $this->showLabel('expand') ? $this->t('Expand') : '',
      'expand',
      $this->showIcon('expand') ? 'arrows-fullscreen' : '',
      $this->t('Expand to cover the viewport. (shortcut: Shift+E)'),
      ['shift+e' => $this->t('Toggle expand')]
    );
    // Required for the library to work.
    $expand['#attributes']['data-set-expand'] = TRUE;

    return $expand;
  }

  /**
   * Builds the theme menu component.
   *
   * @return array
   *   The theme menu component render array.
   */
  private function buildThemeMenu(): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:theme_menu',
      '#attributes' => [
        'placement' => 'bottom-end',
        'data-theme-switch' => TRUE,
      ],
      '#slots' => [
        'button' => $this->buildButton(
          $this->showLabel('theme') ? $this->t('Theme') : '',
          'theme',
          $this->showIcon('theme') ? 'sun' : '',
        ),
      ],
      '#props' => [
        'tooltip' => $this->t('Switch the display builder interface theme.'),
        'icon' => 'sun',
      ],
    ];
  }

  /**
   * Builds the keyboard button.
   *
   * @return array
   *   The keyboard button render array.
   */
  private function buildKeyboardButton(): array {
    return $this->buildButton(
      $this->showLabel('help') ? $this->t('Help') : '',
      'help',
      $this->showIcon('help') ? 'question-circle' : '',
      '...'
    );
  }

}
