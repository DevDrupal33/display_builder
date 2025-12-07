<?php

declare(strict_types=1);

namespace Drupal\display_builder;

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

  /**
   * Set the builder id.
   *
   * @param string $builder_id
   *   The builder id.
   *
   * @todo remove this, should be on base class
   */
  public function setBuilderId(string $builder_id): void {
    $this->builderId = $builder_id;
  }

  /**
   * Set the tree node ID.
   *
   * @param string|null $node_id
   *   The tree node ID.
   *
   * @todo remove this, should be on base class
   */
  public function setNodeId(?string $node_id): void {
    $this->nodeId = $node_id;
  }

}
