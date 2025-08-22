<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\Entity\PatternPreset;

/**
 * Pattern preset form.
 */
final class PatternPresetForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [PatternPreset::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
      '#rows' => 2,
    ];

    $form['group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group'),
      '#default_value' => $entity->get('group'),
    ];

    $form['sources'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sources'),
      '#default_value' => Yaml::encode($entity->get('sources') ?? []),
      '#rows' => 16,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        SAVED_NEW => $this->t('Created new preset %label.', $message_args),
        SAVED_UPDATED => $this->t('Updated preset %label.', $message_args),
        default => '',
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    $sources = $form_state->getValue('sources');

    try {
      $sources = \is_string($sources) ? Yaml::decode($sources) : $sources;
    }
    catch (InvalidDataTypeException $e) {
      // We do it here instead of FormInterface::validateForm() because it is
      // the earliest call of EntityInterface::set().
      $form_state->setErrorByName('sources', $this->t('The import failed with the following message: %message', ['%message' => $e->getMessage()]));
    }
    $form_state->setValue('sources', $sources);

    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

}
