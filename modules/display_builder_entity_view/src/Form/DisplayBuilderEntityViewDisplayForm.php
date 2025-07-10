<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplayStorage;
use Drupal\layout_builder\Form\LayoutBuilderEntityViewDisplayForm;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Edit form for the DisplayBuilderEntityViewDisplay entity type.
 *
 * @internal
 *   Form classes are internal.
 */
final class DisplayBuilderEntityViewDisplayForm extends LayoutBuilderEntityViewDisplayForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay
   */
  protected $entity;

  /**
   * The storage source.
   */
  protected ?DisplayBuilderEntityViewDisplayStorage $sourceStorage;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, mixed $source_storage = NULL) {
    $this->sourceStorage = $source_storage;

    return parent::buildForm($form, $form_state, $section_storage);
  }

  /**
   * Alter the entity form form state values.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay $display
   *   The entity display.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public function displayBuilderEntityFormEntityBuild(string $entity_type_id, DisplayBuilderEntityViewDisplay $display, array &$form, FormStateInterface &$form_state): void {
    $set_enabled = (bool) $form_state->getValue(['display_builder', 'enabled'], FALSE);
    $already_enabled = $display->isDisplayBuilderEnabled();

    if ($set_enabled) {
      if (!$already_enabled) {
        $display->enableDisplayBuilder();
      }
    }
    elseif ($already_enabled) {
      $display->disableDisplayBuilder();
      // @todo Implements a confirmation step to disable Display Builder like
      // with LayoutBuilderDisableForm.
      // $form_state->setRedirectUrl($this->sourceStorage->getDisplayBuilderUrl('disable'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $is_layout_builder_enabled = $this->entity->isLayoutBuilderEnabled();
    $is_display_builder_enabled = $this->entity->isDisplayBuilderEnabled();

    if ($is_display_builder_enabled) {
      // Hide the table of fields.
      $form['fields']['#access'] = FALSE;
      $form['#fields'] = [];
      $form['#extra'] = [];
    }

    $form['manage_display_builder'] = [
      '#type' => 'link',
      '#title' => $this->t('Display builder'),
      '#weight' => -11,
      '#attributes' => ['class' => ['button']],
      '#url' => $this->getDisplayBuilderUrl(),
      '#access' => $is_display_builder_enabled,
    ];

    if (isset($form['modes'])) {
      $form['modes']['#weight'] = 0;
    }

    if (isset($form['layout'])) {
      $form['layout']['#weight'] = 2;
      $form['layout']['#open'] = $is_layout_builder_enabled;
    }

    $form['display_builder'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Display Builder options'),
      '#tree' => TRUE,
      '#weight' => 1,
    ];

    if ($is_layout_builder_enabled && $is_display_builder_enabled) {
      $form['display_builder']['status'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('Layout Builder is enabled and will handle this display. Display Builder can still be used but will not handle the display until Layout builder is disabled.'),
          ],
        ],
      ];
    }

    $form['display_builder']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Display Builder'),
      '#default_value' => $is_display_builder_enabled,
    ];

    $form['#entity_builders']['display_builder'] = '::displayBuilderEntityFormEntityBuild';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayBuilderUrl(string $rel = 'view'): Url {
    return Url::fromRoute("display_builder.{$this->entity->getTargetEntityTypeId()}.{$rel}", $this->getRouteParameters());
  }

  /**
   * {@inheritdoc}
   */
  protected function buildExtraFieldRow($field_id, $extra_field): array {
    if ($this->entity->isDisplayBuilderEnabled()) {
      return [];
    }

    return parent::buildExtraFieldRow($field_id, $extra_field);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildFieldRow(FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state): array {
    if ($this->entity->isDisplayBuilderEnabled()) {
      return [];
    }

    return parent::buildFieldRow($field_definition, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    // Do not process field values if Display Builder is or will be enabled.
    $set_enabled = (bool) $form_state->getValue(['display_builder', 'enabled'], FALSE);
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay $entity */
    $already_enabled = $entity->isDisplayBuilderEnabled();

    if ($already_enabled || $set_enabled) {
      $form['#fields'] = [];
      $form['#extra'] = [];
    }

    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * Provides the route parameters needed to generate a URL for this object.
   *
   * @return mixed[]
   *   An associative array of parameter names and values.
   */
  protected function getRouteParameters() {
    $display = $this->entity;
    $entity_type = $this->entityTypeManager->getDefinition($display->getTargetEntityTypeId());
    $bundle_parameter_key = $entity_type->getBundleEntityType() ?: 'bundle';

    return [
      $bundle_parameter_key => $display->getTargetBundle(),
      'view_mode_name' => $display->getMode(),
    ];
  }

}
