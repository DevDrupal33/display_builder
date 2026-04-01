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

    if ($this->isButtonEnabled('highlight')) {
      $buttons[] = $this->buildHighlightButton();
      $library[] = 'display_builder/highlight';
    }

    if ($this->isButtonEnabled('fullscreen')) {
      $buttons[] = $this->buildFullscreenButton();
      $library[] = 'display_builder/fullscreen';
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
      'highlight' => [
        'title' => $this->t('Highlight'),
        'description' => $this->t('Highlight builder zones to ease drag and move around.'),
        'default' => 'icon',
      ],
      'fullscreen' => [
        'title' => $this->t('Fullscreen'),
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
   * Builds the highlight button.
   *
   * @return array
   *   The highlight button render array.
   */
  private function buildHighlightButton(): array {
    $highlight = $this->buildButton(
      $this->showLabel('highlight') ? $this->t('Highlight') : '',
      'highlight',
      $this->showIcon('highlight') ? 'border' : '',
      $this->t('Highlight components. (shortcut: H)'),
      ['H' => $this->t('Toggle highlight (shift+H)')]
    );
    // Required for the library to work.
    $highlight['#attributes']['data-set-highlight'] = TRUE;

    return $highlight;
  }

  /**
   * Builds the fullscreen button.
   *
   * @return array
   *   The fullscreen button render array.
   */
  private function buildFullscreenButton(): array {
    $fullscreen = $this->buildButton(
      $this->showLabel('fullscreen') ? $this->t('Fullscreen') : '',
      'fullscreen',
      $this->showIcon('fullscreen') ? 'fullscreen' : '',
      $this->t('Toggle fullscreen. (shortcut: F)'),
      ['F' => $this->t('Toggle fullscreen (shift+F)')]
    );
    // Required for the library to work.
    $fullscreen['#attributes']['data-set-fullscreen'] = TRUE;

    return $fullscreen;
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
