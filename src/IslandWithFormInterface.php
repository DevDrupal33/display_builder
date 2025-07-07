<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for island plugins with a form.
 */
interface IslandWithFormInterface {

  /**
   * Get The form class for this Island.
   *
   * @return string
   *   The Class string
   */
  public static function getFormClass(): string;

  /**
   * Is this Island has form class ?
   *
   * @return bool
   *   TRUE if Island has form class, FALSE otherwise.
   */
  public static function hasFormClass(): bool;

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Alter form element after its built.
   *
   * @param array $element
   *   An associative array containing the structure of the form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The altered form element.
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array;

  /**
   * Set builder id used for this plugin.
   *
   * @param string $builder_id
   *   The builder id.
   */
  public function setBuilderId(string $builder_id): void;

  /**
   * Set instance id used for this plugin.
   *
   * @param string|null $instance_id
   *   The instance id.
   */
  public function setInstanceId(?string $instance_id): void;

}
