<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
    $filters = $this->getSessionFilters();
    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter instances'),
      '#open' => $this->isSessionFilters(),
      '#attributes' => ['class' => ['container-inline']],
    ];

    // Context options from providers.
    $context_options = ['' => $this->t('- Any -')];

    foreach ($providers as $provider) {
      $context_options[$provider['prefix']] = $provider['label'];
    }

    $form['filters']['context'] = [
      '#type' => 'select',
      '#title' => $this->t('Context'),
      '#title_display' => 'invisible',
      '#options' => $context_options,
      '#default_value' => $filters['context'] ?? '',
    ];

    $form['filters']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instance name'),
      '#placeholder' => $this->t('Instance contains'),
      '#title_display' => 'invisible',
      '#default_value' => $filters['name'] ?? '',
      '#size' => 30,
    ];

    $form['filters']['sort'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#title_display' => 'invisible',
      '#options' => [
        'updated_desc' => $this->t('Sort updated desc'),
        'updated_asc' => $this->t('Sort updated asc'),
        'id' => $this->t('Sort by id'),
      ],
      '#default_value' => $filters['sort'] ?? 'updated_desc',
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['container-inline']],
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
    $session_filters = $this->getRequest()->getSession()->get('db_instances_overview_filter', []);
    $values = $form_state->getValues();

    $session_filters['context'] = $values['context'];
    $session_filters['name'] = $values['name'];
    $session_filters['sort'] = $values['sort'];

    $this->getRequest()->getSession()->set('db_instances_overview_filter', $session_filters);
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
    $this->getRequest()->getSession()->remove('db_instances_overview_filter');
  }

  /**
   * Retrieve filter values from the current request (GET).
   *
   * @return array
   *   Returns array with keys: context, name, sort.
   */
  private function getSessionFilters(): array {
    $filters = $this->getRequest()->getSession()->get('db_instances_overview_filter', []);

    return [
      'context' => isset($filters['context']) ? (string) $filters['context'] : '',
      'name' => isset($filters['name']) ? (string) $filters['name'] : '',
      'sort' => isset($filters['sort']) ? (string) $filters['sort'] : '',
    ];
  }

  /**
   * Check if we have saved filters.
   *
   * @return bool
   *   TRUE if we have any saved filters, FALSE otherwise.
   */
  private function isSessionFilters(): bool {
    $filters = $this->getSessionFilters();

    foreach ($filters as $filter) {
      if (!empty($filter)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
