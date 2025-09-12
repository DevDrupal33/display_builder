<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
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
class ControlsButtons extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'toggle_highlight' => TRUE,
      'toggle_fullscreen' => TRUE,
      'keyboard_help' => FALSE,
      'theme_mode' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
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
    $configuration = $this->getConfiguration();

    return \array_filter([
      $configuration['toggle_highlight'] ? $this->t('With toggle highlight.') : '',
      $configuration['toggle_fullscreen'] ? $this->t('With toggle fullscreen.') : '',
      $configuration['keyboard_help'] ? $this->t('With keyboard help.') : '',
      $configuration['theme_mode'] ? $this->t('With theme mode selector.') : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $configuration = $this->getConfiguration();
    $buttons = $library = [];

    if ($configuration['toggle_highlight']) {
      $highlight = $this->buildButton('', 'highlight', 'border', $this->t('Highlight components. (shortcut: H)'), ['H' => $this->t('Toggle highlight (shift+H)')]);
      // Required for the library to work.
      $highlight['#attributes']['data-set-highlight'] = TRUE;
      $library[] = 'display_builder/highlight';
      $buttons[] = $highlight;
    }

    if ($configuration['toggle_fullscreen']) {
      $fullscreen = $this->buildButton('', 'fullscreen', 'fullscreen', $this->t('Toggle fullscreen. (shortcut: F)'), ['F' => $this->t('Toggle fullscreen (shift+M)')]);
      // Required for the library to work.
      $fullscreen['#attributes']['data-set-fullscreen'] = TRUE;
      $library[] = 'display_builder/fullscreen';
      $buttons[] = $fullscreen;
    }

    if ($configuration['theme_mode']) {
      $buttons[] = [
        '#type' => 'component',
        '#component' => 'display_builder:theme_menu',
        '#attributes' => [
          'placement' => 'bottom-end',
          'data-theme-switch' => TRUE,
        ],
        '#slots' => [
          'button' => $this->buildButton('', 'theme', 'sun'),
        ],
        '#props' => [
          'tooltip' => $this->t('Switch the display builder interface theme.'),
          'icon' => 'sun',
        ],
      ];
    }

    if ($configuration['keyboard_help']) {
      $keyboard = $this->buildButton('', 'help', 'question-circle', '...');
      $buttons[] = $keyboard;
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

}
