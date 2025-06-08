<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a display builder form.
 */
final class BlockStylesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_block_styles';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $data = $form_state->getBuildInfo()['args'][0];

    // The data injected in buildInfo,
    // is the one produced by form api.
    // This is not the raw data for the form structure.
    // There is this 'styles' key in the form values,
    // which is specific to this form, not the ui_styles_styles form.
    // The data to be received here is in the form of:
    // ['styles' => ['selected' => [], 'extra' => '']].
    return [
      'styles' => [
        '#type' => 'ui_styles_styles',
        '#title' => $this->t('Styles'),
        '#wrapper_type' => 'div',
        '#default_value' => \array_merge(['selected' => [], 'extra' => ''], $data['styles'] ?? []),
      ],
      '#tree' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Those two lines are necessary to prevent the form from being rebuilt.
    // if rebuilt, the form state values will have both the computed ones
    // and the raw ones (wrapper key and values).
    $form_state->setRebuild(FALSE);
    $form_state->setExecuted();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
