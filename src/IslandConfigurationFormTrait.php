<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add methods to make plugin configuration forms.
 */
trait IslandConfigurationFormTrait {

  /**
   * Validate the Island configuration.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Submit the island configuration.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $configuration = [];

    foreach ($values as $key => $value) {
      $configuration[$key] = $value;
    }

    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($configuration);
    }
  }

}
