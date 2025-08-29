<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for filtering Display Builder instances.
 */
class DisplayBuilderUiFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_ui_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $type_options = [
      '' => $this->t('- All -'),
      'entity_view_override' => $this->t('Entity view override'),
      'entity_view' => $this->t('Entity view'),
      'other' => $this->t('Other'),
      'page_layout' => $this->t('Page layout'),
      'views' => $this->t('Views'),
    ];

    $form['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by type'),
      '#options' => $type_options,
      '#default_value' => $this->getRequest()->query->get('display') ?? '',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $type = $form_state->getValue('display');
    $form_state->setRedirect('<current>', [], ['query' => ['display' => $type]]);
  }

}
