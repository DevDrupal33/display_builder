<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Defines an add display builder instance form.
 */
final class AddForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storage = $this->entityTypeManager->getStorage('display_builder');
    $displayBuilderConfig = $storage->loadMultiple();

    $options = [];

    foreach ($displayBuilderConfig as $config) {
      $options[$config->id()] = $config->label();
    }
    $form['display_builder_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Display builder'),
      '#description' => $this->t('Choose a display builder configuration. Create a <a href="@url">new configuration</a> if needed.', ['@url' => Url::fromRoute('entity.display_builder.add_form')->toString()]),
      '#options' => $options,
      '#default_value' => reset($options),
      '#required' => TRUE,
    ];

    $options = DisplayBuilderHelpers::getAllFixturesOptions();

    $form['fixture_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial test data'),
      '#description' => $this->t('Enter the fixture to use as base for this display builder instance.'),
      '#options' => $options,
      '#default_value' => 'blank',
      '#required' => TRUE,
    ];

    $form['builder_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Builder ID'),
      '#default_value' => \sprintf('db_%s', uniqid()),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
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
    return 'display_builder_devel_add';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $display_builder_id = $form_state->getValue('display_builder_id');
    $fixture_id = $form_state->getValue('fixture_id', 'blank');
    $builder_id = $form_state->getValue('builder_id');

    $builder_data = DisplayBuilderHelpers::getFixtureData([
      __DIR__ . '/../../fixtures/' . $fixture_id,
      __DIR__ . '/../../../display_builder_page_layout/fixtures/' . $fixture_id,
    ]);

    // Create the display builder instance with this data.
    $this->stateManager->create(
      $builder_id,
      $display_builder_id,
      $builder_data,
      [],
    );

    $form_state->setRedirectUrl(
      new Url(
        'display_builder_devel.view',
        [
          'builder_id' => $builder_id,
        ]
      )
    );
  }

}
