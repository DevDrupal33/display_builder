<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Confirmation form to confirm deletion of display builder instance by id.
 */
class ConfirmDeleteForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * Display builder id to delete.
   */
  private ?string $builderId;

  public function __construct(
    private StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL): array {
    $this->builderId = $builder_id;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('display_builder_devel.collection', ['builder_id' => $this->builderId]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'confirm_display_builder_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you want to delete %id?', ['%id' => $this->builderId]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->stateManager->delete($this->builderId);
    $form_state->setRedirectUrl(new Url('display_builder_devel.collection'));
  }

}
