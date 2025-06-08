<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Defines a confirmation form to confirm deletion of all display builder.
 */
class ConfirmDeleteAllForm extends ConfirmFormBase {

  use AutowireTrait;

  public function __construct(
    private readonly StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('display_builder_devel.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'confirm_display_builder_delete_all_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete all display builder demo(s) created?');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->stateManager->deleteAll();
    $form_state->setRedirectUrl(new Url('display_builder_devel.collection'));
  }

}
