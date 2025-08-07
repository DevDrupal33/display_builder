<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_ui\Form\EntityViewDisplayEditForm;

/**
 * Edit form for the entity view display entity type.
 *
 * @internal
 *   Form classes are internal.
 */
final class EntityViewDisplayForm extends EntityViewDisplayEditForm {

  use EntityViewDisplayFormTrait;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\display_builder_entity_view\Entity\EntityViewDisplay
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    return $this->entityViewDisplayForm($form);
  }

}
