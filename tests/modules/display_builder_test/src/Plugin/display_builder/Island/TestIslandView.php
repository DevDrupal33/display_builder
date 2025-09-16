<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
// Use Drupal\Core\Plugin\PluginFormInterface;.
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandConfigurationFormInterface;
use Drupal\display_builder\IslandConfigurationFormTrait;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Test island plugin for testing purposes.
 */
#[Island(
  id: 'test_island_view',
  label: new TranslatableMarkup('Test Island View'),
  description: new TranslatableMarkup('A test island for testing configuration.'),
  type: IslandType::View,
)]
class TestIslandView extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();

    $form['string_value'] = [
      '#title' => $this->t('string_value'),
      '#type' => 'textfield',
      '#default_value' => $config['string_value'],
    ];

    $form['bool_value'] = [
      '#title' => $this->t('bool_value'),
      '#type' => 'checkbox',
      '#default_value' => $config['bool_value'],
    ];

    $form['string_array'] = [
      '#title' => $this->t('string_array'),
      '#type' => 'checkboxes',
      '#options' => ['foo' => 'Foo', 'bar' => 'Bar', 'baz' => 'Baz'],
      '#default_value' => $config['string_array'],
    ];

    return $form;
  }

  /**
   * Validate the Island configuration.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Be sure to remove unchecked from values.
    $form_state->setValue('string_array', \array_filter($form_state->getValue('string_array')));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'string_value' => '',
      'bool_value' => 0,
      'string_array' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $summary = parent::configurationSummary();
    $config = $this->getConfiguration();

    if (!empty($config['string_value'])) {
      $summary[] = $this->t('String value: @value', ['@value' => $config['string_value']]);
    }

    if (isset($config['bool_value'])) {
      $summary[] = $this->t('Boolean value: @value', ['@value' => $config['bool_value'] ? 'true' : 'false']);
    }

    if (!empty($config['string_array'])) {
      $summary[] = $this->t('Array values: @values', ['@values' => \implode(', ', $config['string_array'])]);
    }

    return $summary;
  }

}
