<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\IslandWithFormInterface;

/**
 * Provides a display builder form for island plugin.
 */
final class IslandFormBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_island';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $island_args = $form_state->getBuildInfo()['args'][0];
    $plugin = IslandFormBase::getPlugin($island_args);

    if (!$plugin instanceof IslandWithFormInterface) {
      return $form;
    }
    $plugin->setBuilderId($island_args['builder_id']);
    $plugin->buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $island_args = $form_state->getBuildInfo()['args'][0];
    $plugin = IslandFormBase::getPlugin($island_args);

    if (!$plugin instanceof IslandWithFormInterface) {
      return;
    }
    $plugin->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $island_args = $form_state->getBuildInfo()['args'][0];
    $plugin = IslandFormBase::getPlugin($island_args);

    if (!$plugin instanceof IslandWithFormInterface) {
      return;
    }
    $plugin->submitForm($form, $form_state);
  }

  /**
   * Get Island plugin.
   *
   * @param array $args
   *   Arguments from form_state which allow to load plugin.
   *
   * @return object|\Drupal\display_builder\IslandInterface
   *   The Island plugin.
   */
  protected static function getPlugin(array $args) {
    return \Drupal::service('plugin.manager.db_island')->createInstance($args['island_id'], $args['instance']);
  }

  // /**
  //   * Process callback: assigns weights and hides extra fields.
  //   *
  //   * @see \Drupal\Core\Entity\EntityForm::form()
  //   */
  //  public function processForm($element, FormStateInterface $form_state, $form) {
  //    // If the form is cached, process callbacks may not have a valid reference
  //    // to the entity object, hence we must restore it.
  //    $this->entity = $form_state->getFormObject()->getEntity();
  //
  //    return $element;
  //  }
  //
  //  /**
  //   * {@inheritdoc}
  //   */
  //  public function getEntity() {
  //    return $this->entity;
  //  }
  //
  //  /**
  //   * {@inheritdoc}
  //   */
  //  public function save(array $form, FormStateInterface $form_state) {
  //    return $this->entity->save();
  //  }
}
