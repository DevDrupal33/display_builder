<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

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
    $flatValues = [];

    // Define keys that represent structural wrappers without data
    // You might need to add 'container' or others depending on your forms.
    $wrappers = ['fieldset', 'details', 'container'];

    foreach ($values as $key => $value) {
      // Check if this top-level key corresponds to a wrapper element in the
      // form, we check the $form array to confirm the type.
      if (isset($form[$key]['#type']) && \in_array($form[$key]['#type'], $wrappers, TRUE)) {
        // If it's a wrapper, merge its children into the main array.
        if (\is_array($value)) {
          $flatValues = \array_merge($flatValues, $value);
        }
      }
      else {
        // Otherwise, keep the value as is.
        $flatValues[$key] = $value;
      }
    }

    $configuration = [];

    foreach ($flatValues as $key => $value) {
      if (($key === 'exclude' || $key === 'status') && \is_array($value)) {
        // Remove unchecked values.
        $value = \array_filter($value);
      }
      $configuration[$key] = $value;
    }

    if ($this instanceof ConfigurableInterface) {
      $this->setConfiguration($configuration);
    }
  }

}
