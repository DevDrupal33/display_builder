<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\Form\IslandFormBase;

/**
 * Add methods to make plugin forms available.
 */
trait IslandPluginFormTrait {

  /**
   * {@inheritDoc}
   */
  public static function getFormClass(): string {
    return IslandFormBase::class;
  }

  /**
   * Check form class is defined.
   *
   * @return bool
   *   TRUE if form class is defined, FALSE otherwise.
   */
  public static function hasFormClass(): bool {
    return !empty(self::getFormClass());
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameters)
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameters)
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameters)
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function setBuilderId(string $builder_id): void {
    $this->builderId = $builder_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setInstanceId(?string $instance_id): void {
    $this->instanceId = $instance_id;
  }

}
