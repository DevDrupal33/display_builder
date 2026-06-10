<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder_ui\InstanceListBuilder;

/**
 * Provides the instance filter form.
 *
 * @internal
 */
final class InstanceListFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'instance_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $providers = NULL): array {
    $filters = InstanceListBuilder::getSessionFilters($this->getRequest()->getSession());
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'db-instances-filters',
        ],
      ],
    ];

    // Context options from providers.
    $context_options = ['' => $this->t('- Any -')];

    foreach ($providers as $id => $provider) {
      $context_options[$id] = $provider['label'];
    }

    $form['filters']['context'] = [
      '#type' => 'select',
      '#title' => $this->t('Context'),
      '#options' => $context_options,
      '#default_value' => $filters['context'] ?? '',
    ];

    $form['filters']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instance'),
      '#placeholder' => $this->t('Contains characters...'),
      '#default_value' => $filters['name'] ?? '',
      '#size' => 30,
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    if (!empty($filters['context']) || !empty($filters['name'])) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => [],
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();
    $state = $session->get('db_instances_overview', []);
    $values = $form_state->getValues();
    $state['filters'] = [
      'context' => $values['context'],
      'name' => $values['name'],
    ];
    $session->set('db_instances_overview', $state);
  }

  /**
   * Resets the filter form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();
    $state = $session->get('db_instances_overview', []);
    unset($state['filters']);
    $session->set('db_instances_overview', $state);
  }

}
