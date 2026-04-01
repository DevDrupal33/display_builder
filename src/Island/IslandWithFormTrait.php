<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\Form\IslandFormBase;

/**
 * Add methods to make plugin forms available.
 */
trait IslandWithFormTrait {

  /**
   * Get the form class.
   *
   * @return string
   *   The form class.
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
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
