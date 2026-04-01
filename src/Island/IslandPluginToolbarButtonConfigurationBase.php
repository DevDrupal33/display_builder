<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for island plugins toolbar with button.
 *
 * This class provide a default configuration form to enable/disable
 * the label and icon for each button provided by the plugin.
 */
abstract class IslandPluginToolbarButtonConfigurationBase extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  public const KEY_VALUE = 'value';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = [];

    foreach ($this->hasButtons() as $button_id => $button) {
      $configuration[$button_id][self::KEY_VALUE] = $button['default'] ?? 'label';
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $options = [
      'label' => $this->t('Label'),
      'icon' => $this->t('Icon'),
      'icon_label' => $this->t('Icon + Label'),
      'hidden' => $this->t('Hidden'),
    ];

    foreach ($this->hasButtons() as $button_id => $button) {
      $default_value = $configuration[$button_id][self::KEY_VALUE] ?? $button['default'] ?? 'hidden';
      $form[$button_id][self::KEY_VALUE] = [
        '#type' => 'select',
        '#title' => $this->t('@name button', ['@name' => $button['title']]),
        '#options' => $options,
        '#default_value' => $default_value,
        '#description' => $button['description'] ?? '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();
    $buttons = [];

    foreach ($this->hasButtons() as $button_id => $button) {
      if (empty($configuration[$button_id]['value'])) {
        continue;
      }

      if ($summary = $this->getButtonSummary($button_id, $button, $configuration[$button_id])) {
        $buttons[] = $summary;
      }
    }

    if (empty($buttons)) {
      return [$this->t('No buttons. Will not be displayed.')];
    }

    return [
      $this->t('Buttons: @buttons.', ['@buttons' => \implode(', ', $buttons)]),
    ];
  }

  /**
   * Get summary of a single button.
   *
   * @param string $button_id
   *   The button ID.
   * @param array $definition
   *   The button definition according to ::hasButtons()
   * @param array $configuration
   *   The button current configuration.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The configuration summary of the button.
   */
  protected function getButtonSummary(string $button_id, array $definition, array $configuration): ?TranslatableMarkup {
    $value = $configuration[self::KEY_VALUE] ?? 'label';
    $title = $definition['title'] ?? $button_id;

    return match ($value) {
      'label' => $this->t('@button (label only)', ['@button' => $title]),
      'icon' => $this->t('@button (icon only)', ['@button' => $title]),
      'icon_label' => $this->t('@button (icon and label)', ['@button' => $title]),
      default => NULL,
    };
  }

  /**
   * Is the button enabled?
   *
   * @param string $button_id
   *   The button ID.
   *
   * @return bool
   *   The button is enabled if it has a label or an icon.
   */
  protected function isButtonEnabled(string $button_id): bool {
    $value = $this->getButtonValue($button_id);

    return $value !== NULL && $value !== 'hidden';
  }

  /**
   * Shows if the label is enabled for a given button.
   *
   * @param string $button_id
   *   The button ID.
   *
   * @return bool
   *   TRUE if the label is enabled, FALSE otherwise.
   */
  protected function showLabel(string $button_id): bool {
    $value = $this->getButtonValue($button_id);

    return $value === 'label' || $value === 'icon_label';
  }

  /**
   * Shows if the label is enabled for a given button.
   *
   * @param string $button_id
   *   The button ID.
   *
   * @return bool
   *   TRUE if the label is enabled, FALSE otherwise.
   */
  protected function showIcon(string $button_id): bool {
    $value = $this->getButtonValue($button_id);

    return $value === 'icon' || $value === 'icon_label';
  }

  /**
   * Returns the list of buttons provided by this plugin.
   *
   * Each button is an array with two keys: 'label' and 'icon', both boolean.
   * The keys indicate if the label or icon can be configured to be shown.
   * For example:
   *
   * @code
   * return [
   *  'my_button_id' => ['label' => TRUE, 'icon' => FALSE],
   * 'my_other_button_id' => ['label' => TRUE, 'icon'=> TRUE],
   * ];
   *
   * @endcode
   *
   * @return array
   *   An associative array of button IDs and their configuration options.
   */
  protected function hasButtons(): array {
    return [];
  }

  /**
   * Gets the configuration value for a given button.
   *
   * @param string $button_id
   *   The button ID.
   *
   * @return string|null
   *   The configuration value, or null if not set.
   */
  private function getButtonValue(string $button_id): ?string {
    $configuration = $this->getConfiguration();

    return $configuration[$button_id][self::KEY_VALUE] ?? NULL;
  }

}
