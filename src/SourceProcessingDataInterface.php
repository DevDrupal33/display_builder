<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_patterns\SourceInterface;

/**
 * Defines an interface for sources storing part of their data elsewhere.
 *
 * A source implementing it gets a chance to divert form values to their real
 * owner, a view display for example, and to keep the source tree free of the
 * keys it just handled.
 */
interface SourceProcessingDataInterface extends SourceInterface {

  /**
   * Validates form values the source will divert to their real owner.
   *
   * Called while the island form is still validating, so an error here stops
   * the save.
   *
   * @param array $form
   *   The island form, as built for this source.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form the values come from.
   */
  public function validateFormData(array $form, FormStateInterface $form_state): void;

  /**
   * Processes form data before it is saved to the source tree.
   *
   * @param array $data
   *   The validated form values for this source.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form the values come from.
   *
   * @return array
   *   The values to store in the source tree.
   */
  public function processFormData(array $data, FormStateInterface $form_state): array;

}
