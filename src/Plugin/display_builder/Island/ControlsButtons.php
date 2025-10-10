<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\IslandType;

/**
 * Controls button island plugin implementation.
 */
#[Island(
  id: 'controls',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Controls'),
  description: new TranslatableMarkup('Control the building experience.'),
  type: IslandType::Button,
)]
class ControlsButtons extends IslandPluginToolbarButtonConfigurationBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration();

    return \array_merge($configuration, [
      'toggle_highlight' => TRUE,
      'toggle_fullscreen' => TRUE,
      'keyboard_help' => FALSE,
      'theme_mode' => FALSE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $configuration = $this->getConfiguration();

    $form['toggle_highlight'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Toggle highlight'),
      '#description' => $this->t('Toggle the builder highlight zones to ease drag and move around.'),
      '#default_value' => $configuration['toggle_highlight'],
    ];
    $form['toggle_fullscreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Toggle fullscreen'),
      '#description' => $this->t('Toggle the builder as fullscreen.'),
      '#default_value' => $configuration['toggle_fullscreen'],
    ];
    $form['keyboard_help'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keyboard help'),
      '#description' => $this->t('Information on the available keyboard shortcuts.'),
      '#default_value' => $configuration['keyboard_help'],
    ];
    $form['theme_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Theme mode selector'),
      '#description' => $this->t('Pick a theme mode as light/dark/system for the display builder.'),
      '#default_value' => $configuration['theme_mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $summary = [];
    $configuration = $this->getConfiguration();

    $control_map = [
      'toggle_highlight' => $this->t('highlight'),
      'toggle_fullscreen' => $this->t('fullscreen'),
      'keyboard_help' => $this->t('help'),
      'theme_mode' => $this->t('theme mode'),
    ];

    $enabled_controls = [];

    foreach ($control_map as $config_key => $label) {
      if (!empty($configuration[$config_key])) {
        $enabled_controls[] = $label;
      }
    }

    if (!empty($enabled_controls)) {
      $summary[] = $this->t('Visible controls: %list', [
        '%list' => \implode(', ', $enabled_controls),
      ]);
    }

    return \array_merge($summary, parent::configurationSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function hasButtons(): array {
    return [
      'highlight' => ['label' => FALSE, 'icon' => TRUE],
      'fullscreen' => ['label' => FALSE, 'icon' => TRUE],
      'theme' => ['label' => FALSE, 'icon' => TRUE],
      'help' => ['label' => FALSE, 'icon' => TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $configuration = $this->getConfiguration();
    $buttons = [];
    $library = [];

    if ($configuration['toggle_highlight']) {
      $buttons[] = $this->buildHighlightButton();
      $library[] = 'display_builder/highlight';
    }

    if ($configuration['toggle_fullscreen']) {
      $buttons[] = $this->buildFullscreenButton();
      $library[] = 'display_builder/fullscreen';
    }

    if ($configuration['theme_mode']) {
      $buttons[] = $this->buildThemeMenu();
    }

    if ($configuration['keyboard_help']) {
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
