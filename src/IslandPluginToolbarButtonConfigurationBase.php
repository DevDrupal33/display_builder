<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for island plugins toolbar with button.
 *
 * This class provide a default configuration form to enable/disable
 * the label and icon for each button provided by the plugin.
 */
abstract class IslandPluginToolbarButtonConfigurationBase extends IslandPluginBase implements IslandPluginToolbarButtonConfigurationInterface {

  use IslandConfigurationFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = [];

    foreach ($this->hasButtons() as $button_id => $default) {
      $configuration[$button_id] = ['label' => $default['label'] ?? FALSE, 'icon' => $default['icon'] ?? TRUE];
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    foreach (\array_keys($this->hasButtons()) as $button_id) {
      $form[$button_id]['label'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show %id label', ['%id' => $button_id]),
        '#default_value' => $configuration[$button_id]['label'],
      ];

      $form[$button_id]['icon'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show %id icon', ['%id' => $button_id]),
        '#default_value' => $configuration[$button_id]['icon'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    $icon = [];
    $label = [];

    foreach (\array_keys($this->hasButtons()) as $button_id) {
      if (empty($configuration[$button_id])) {
        continue;
      }

      if (!empty($configuration[$button_id]['label'])) {
        $label[] = $button_id;
      }

      if (!empty($configuration[$button_id]['icon'])) {
        $icon[] = $button_id;
      }
    }

    $summary = [];

    if (!empty($label)) {
      $summary[] = $this->t('Label visible: %list', ['%list' => \implode(', ', $label)]);
    }

    if (!empty($icon)) {
      $summary[] = $this->t('Icon visible: %list', ['%list' => \implode(', ', $icon)]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function showLabel(string $button_id): bool {
    return $this->show($button_id, 'label');
  }

  /**
   * {@inheritdoc}
   */
  public function showIcon(string $button_id): bool {
    return $this->show($button_id, 'icon');
  }

  /**
   * {@inheritdoc}
   */
  public function hasButtons(): array {
    return [];
  }

  /**
   * Show configuration value for label or icon.
   *
   * @param string $button_id
   *   The button ID.
   * @param string $name
   *   The name of the configuration value, either 'label' or 'icon'.
   *
   * @return bool
   *   TRUE if the configuration value is set to TRUE, FALSE otherwise.
   */
  private function show(string $button_id, string $name): bool {
    $configuration = $this->getConfiguration();

    if (!isset($configuration[$button_id])) {
      switch ($name) {
        case 'label':
          return TRUE;

        default:
          return FALSE;
      }
    }

    return (bool) $configuration[$button_id][$name];
  }

}
