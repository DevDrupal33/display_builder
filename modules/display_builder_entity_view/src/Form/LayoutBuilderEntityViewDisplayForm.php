<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\LayoutBuilderEntityViewDisplayForm as CoreLayoutBuilderEntityViewDisplayForm;

/**
 * Edit form for the entity view display entity type.
 *
 * @internal
 *   Form classes are internal.
 */
final class LayoutBuilderEntityViewDisplayForm extends CoreLayoutBuilderEntityViewDisplayForm {

  use EntityViewDisplayFormTrait;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form = $this->entityViewDisplayForm($form);

    if (isset($form['layout'])) {
      $form['layout']['#weight'] = 2;
      $form['layout']['#open'] = $this->entity->isLayoutBuilderEnabled();
    }

    return $form;
  }

}
