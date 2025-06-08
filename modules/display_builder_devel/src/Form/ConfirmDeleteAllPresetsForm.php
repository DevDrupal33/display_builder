<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form to delete of all display builder presets.
 */
class ConfirmDeleteAllPresetsForm extends ConfirmFormBase {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.display_builder_preset.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'confirm_display_builder_presets_delete_all_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete all display builder preset(s) created?');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $preset_storage = $this->entityTypeManager->getStorage('display_builder_preset');
    $presets = $preset_storage->loadMultiple();
    $preset_storage->delete($presets);

    $form_state->setRedirectUrl(new Url('entity.display_builder_preset.collection'));
  }

}
