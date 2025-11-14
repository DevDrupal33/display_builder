<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface;

/**
 * Common methods for entity view display form.
 */
trait EntityViewDisplayFormTrait {

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    // @todo we should have always a fallback.
    $display_builder_config = $form_state->getValue([ConfigFormBuilderInterface::PROFILE_PROPERTY]) ?? 'default';

    // Empty means disabled.
    if (empty($display_builder_config)) {
      $this->entity->unsetThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, $display_builder_config);
    }

    $display_builder_override = $form_state->getValue([ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY]) ?? '';

    // Empty means disabled.
    if (empty($display_builder_override)) {
      $this->entity->unsetThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY, $display_builder_override);
    }

    $display_builder_override_profile = $form_state->getValue([ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY]) ?? '';

    // Empty means disabled.
    if (empty($display_builder_override_profile)) {
      $this->entity->unsetThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY, $display_builder_override_profile);
    }

    $this->entity->save();
    $this->localTaskManager->clearCachedDefinitions();
    $this->routeBuilder->rebuild();
  }

  /**
   * Provides form elements to enable Display Builder.
   *
   * @param array $form
   *   The form structure.
   *
   * @return array
   *   The modified form.
   */
  protected function entityViewDisplayForm(array $form): array {
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
      '#url' => $this->entity->getBuilderUrl(),
      '#access' => $is_display_builder_enabled,
    ];

    if (isset($form['modes'])) {
      $form['modes']['#weight'] = 0;
    }

    $form['display_builder_wrapper'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Display builder'),
      '#weight' => 1,
    ];

    $form['display_builder_wrapper'][ConfigFormBuilderInterface::PROFILE_PROPERTY] = $this->configFormBuilder->build($this->entity, FALSE);

    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $entity */
    $entity = $this->getEntity();

    if ($entity instanceof DisplayBuilderOverridableInterface) {
      $form['display_builder_wrapper'][ConfigFormBuilderInterface::PROFILE_PROPERTY]['override_form'] = $this->buildOverridesForm($entity);
    }

    return $form;
  }

  /**
   * Build the form for entity display overrides per content.
   *
   * @param \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $entity
   *   The entity.
   *
   * @return array
   *   The renderable form array.
   */
  protected function buildOverridesForm(DisplayBuilderEntityDisplayInterface|DisplayBuilderOverridableInterface $entity): array {
    $entity_type_id = $entity->getTargetEntityTypeId();
    $options = $this->getSourceFieldAsOptions();
    $target_entity_type_id = $this->entityTypeManager->getDefinition($entity_type_id)->getBundleEntityType();
    $description = [
      '#title' => $this->t('Add a UI Patterns Source field'),
      '#type' => 'link',
      '#attributes' => [
        'data-dialog-type' => 'modal',
        'data-dialog-options' => \json_encode([
          'title' => 'Add field: Source (UI Patterns)',
          'width' => '800',
        ]),
        'class' => ['use-ajax'],
      ],
      '#url' => Url::fromRoute("field_ui.field_storage_config_add_sub_{$entity_type_id}", [
        $target_entity_type_id => $entity->getTargetBundle(),
        'display_as_group' => 'Group',
        'selected_field_type' => 'ui_patterns_source',
      ]),
      '#suffix' => '.',
    ];

    if (empty($options)) {
      $description = [
        [
          '#plain_text' => $this->t('No eligible field found.') . ' ',
        ],
        $description,
      ];
    }
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface $overridable */
    $overridable = $entity;
    $form = [];
    $form[ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY] = [
      '#type' => 'select',
      '#title' => $this->t('Select a field to override this display per content'),
      '#options' => $options,
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $overridable->getDisplayBuilderOverrideField(),
      '#description' => $description,
    ];

    $form[ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY] = [
      '#type' => 'select',
      '#title' => $this->t('Override profile'),
      '#description' => $this->t('The profile used for content overrides.'),
      '#options' => $this->configFormBuilder->getAllowedProfiles(),
      '#default_value' => $overridable->getDisplayBuilderOverrideProfile()?->id(),
      '#states' => [
        'invisible' => [
          ':input[name="' . ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY . '"]' => ['filled' => FALSE],
        ],
      ],
    ];

    if (!$this->configFormBuilder->isAllowed($entity)) {
      $form[ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY]['#disabled'] = TRUE;
      unset($form[ConfigFormBuilderInterface::OVERRIDE_FIELD_PROPERTY]['#description']);
      $form[ConfigFormBuilderInterface::OVERRIDE_PROFILE_PROPERTY]['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * Returns an array of UI Patterns Source fields which are already mapped.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $current_display
   *   The current display.
   *
   * @return array
   *   An array of field names that are already mapped to the current display.
   */
  protected function getAlreadyMappedFields(EntityViewDisplayInterface $current_display): array {
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface[] $displays */
    $displays = $this->entityTypeManager->getStorage('entity_view_display')->loadByProperties([
      'targetEntityType' => $current_display->getTargetEntityTypeId(),
      'bundle' => $current_display->getTargetBundle(),
    ]);
    $field_names = [];

    foreach ($displays as $display) {
      if ($display instanceof DisplayBuilderOverridableInterface) {
        if ($display->isDisplayBuilderOverridable()
          && $current_display->id() !== $display->id()) {
          $field_names[] = $display->getDisplayBuilderOverrideField();
        }
      }
    }

    return $field_names;
  }

  /**
   * Returns UI Patterns source fields as options.
   *
   * @return array
   *   An associative array of field names and labels.
   */
  protected function getSourceFieldAsOptions(): array {
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $display */
    $display = $this->getEntity();
    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      $display->getTargetEntityTypeId(),
      $display->getTargetBundle(),
    );
    $fields = [];

    if ($display instanceof DisplayBuilderOverridableInterface
      && $display instanceof EntityViewDisplayInterface
    ) {
      $already_mapped = $this->getAlreadyMappedFields($display);

      foreach ($field_definitions as $field_name => $field_definition) {
        if ($field_definition->getType() === 'ui_patterns_source'
          && !\in_array($field_name, $already_mapped, TRUE)
        ) {
          $fields[$field_name] = $field_definition->getLabel();
        }
      }
    }

    return $fields;
  }

  /**
   * Builds the table row structure for a single extra field.
   *
   * @param string $field_id
   *   The field ID.
   * @param array $extra_field
   *   The pseudo-field element.
   *
   * @return array
   *   A table row array.
   */
  protected function buildExtraFieldRow($field_id, $extra_field): array {
    if ($this->entity->isDisplayBuilderEnabled()) {
      return [];
    }

    return parent::buildExtraFieldRow($field_id, $extra_field);
  }

  /**
   * Builds the table row structure for a single field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A table row array.
   */
  protected function buildFieldRow(FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state): array {
    if ($this->entity->isDisplayBuilderEnabled()) {
      return [];
    }

    return parent::buildFieldRow($field_definition, $form, $form_state);
  }

  /**
   * Copies top-level form values to entity properties.
   *
   * This should not change existing entity properties that are not being edited
   * by this form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\Core\Form\ConfigFormBase::copyFormValuesToConfig()
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $entity */
    // Do not process field values if Display Builder is or will be enabled.
    $set_enabled = (bool) $form_state->getValue(['display_builder', 'enabled'], FALSE);
    $already_enabled = $entity->isDisplayBuilderEnabled();

    if ($already_enabled || $set_enabled) {
      $form['#fields'] = [];
      $form['#extra'] = [];
    }

    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

}
