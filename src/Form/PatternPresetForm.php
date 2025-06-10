<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

// Use Drupal\Component\Serialization\Yaml;.
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\Entity\PatternPreset;
use Symfony\Component\Yaml\Yaml;

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

    $form['theme'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('The theme this preset is meant to be used with.'),
      '#default_value' => $entity->get('theme'),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
    ];

    $form['group'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group'),
      '#default_value' => $entity->get('group'),
    ];

    $form['sources'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sources'),
      '#default_value' => $entity->get('sources'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $sources = $form_state->getValue('sources');

    try {
      Yaml::parse($sources);
    }
    catch (\Throwable $th) {
      $form_state->setErrorByName(
        'sources',
        $this->t('The value is not correct. @error', ['@error' => $th->getMessage()]),
          );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new preset %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated preset %label.', $message_args),
        default => '',
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
