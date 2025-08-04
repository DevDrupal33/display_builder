<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_devel\FixturesHelpers;
use Drupal\display_builder_devel\MockEntity;

/**
 * Defines an add display builder instance form.
 */
final class AddForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected ConfigFormBuilderInterface $configFormBuilder,
    protected StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $display_builder = new MockEntity('', '', []);
    $form[ConfigFormBuilderInterface::PROFILE_PROPERTY] = $this->configFormBuilder->build($display_builder);

    $form['fixture_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial data'),
      '#description' => $this->t('Enter the fixture to use as base for this display builder instance.'),
      '#options' => FixturesHelpers::getAllFixturesOptions(),
      '#default_value' => 'blank',
      '#required' => TRUE,
    ];

    $form['builder_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Builder ID'),
      '#description' => $this->t('Uniq identifier for this instance.'),
      '#default_value' => \sprintf('db_%s', \uniqid()),
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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $builder_id = $form_state->getValue('builder_id');

    if ($this->stateManager->load($builder_id)) {
      $form_state->setErrorByName(
        'builder_id',
        $this->t('This id already exist, must be uniq.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $display_builder_id = $form_state->getValue(ConfigFormBuilderInterface::PROFILE_PROPERTY);
    $fixture_id = $form_state->getValue('fixture_id', 'blank');
    $builder_id = $form_state->getValue('builder_id');

    $sources = DisplayBuilderHelpers::getFixtureData([
      __DIR__ . '/../../fixtures/' . $fixture_id,
      __DIR__ . '/../../../display_builder_page_layout/fixtures/' . $fixture_id,
    ]);

    $display_builder = new MockEntity($builder_id, $display_builder_id, $sources);
    $display_builder->initInstanceIfMissing();
    $form_state->setRedirectUrl($display_builder->getBuilderUrl());
  }

}
