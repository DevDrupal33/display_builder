<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_devel\FixturesHelpers;

/**
 * Defines an add display builder instance form.
 */
final class ImportForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL): array {
    $options = FixturesHelpers::getAllFixturesOptions();

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Import type'),
      '#options' => ['fixture' => $this->t('Fixture'), 'input' => $this->t('Input')],
      '#default_value' => 'fixture',
      '#required' => TRUE,
    ];

    $form['fixture_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Replace data'),
      '#description' => $this->t('Enter the fixture to replace in this display builder instance.'),
      '#options' => $options,
      '#default_value' => 'blank',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'fixture'],
        ],
      ],
    ];

    $form['builder_id'] = [
      '#type' => 'hidden',
      '#value' => $builder_id,
    ];

    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Import data'),
      '#description' => $this->t('Paste Yaml formatted data to import.'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'input'],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => new Url('display_builder_devel.collection'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_devel_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $import_type = $form_state->getValue('type', 'fixture');

    $builder_id = $form_state->getValue('builder_id');

    if ($import_type === 'fixture') {
      $fixture_id = $form_state->getValue('fixture_id', 'blank');
      $builder_data = FixturesHelpers::getAllFixturesData($fixture_id);
    }
    else {
      $builder_data = Yaml::decode($form_state->getValue('data'));
    }

    $current = $this->stateManager->load($builder_id);
    $contexts = $this->stateManager->getContexts($builder_id);
    $this->stateManager->delete($builder_id);

    $this->stateManager->create(
      $builder_id,
      $current['entity_config_id'],
      $builder_data,
      $contexts,
    );

    // phpcs:ignore
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
  }

}
