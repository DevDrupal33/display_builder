<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Common methods for entity view display form.
 */
trait EntityViewDisplayFormTrait {

  use StringTranslationTrait;

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

    $profile = $form_state->getValue(DisplayBuildableInterface::PROFILE_PROPERTY, '');
    $override_status = (bool) $form_state->getValue('override_status', FALSE);

    // Empty means disable main and override.
    if (empty($profile)) {
      $this->setOverrideFieldLocked(FALSE);
      $this->entity->unsetThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY);
      $this->entity->unsetThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY);
      $this->entity->unsetThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY, $profile);
    }

    if ($override_status) {
      $profile_override = $form_state->getValue(DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY, NULL);

      if ($profile_override) {
        $this->entity->setThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY, $profile_override);
      }

      $override_field = $form_state->getValue(DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY, NULL);

      if (!$override_field) {
        $field_name = \sprintf('display_%s', $this->entity->getMode());
        $field_name = $this->createOverrideField($field_name);
      }
      else {
        $field_name = $override_field;
      }
      // In case of field change, need to unlock the previous field.
      $this->setOverrideFieldLocked(FALSE);
      $this->entity->setThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY, $field_name);
      $this->setOverrideFieldLocked(TRUE);
    }
    else {
      $this->setOverrideFieldLocked(FALSE);
      $this->entity->unsetThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY);
      $this->entity->unsetThirdPartySetting('display_builder', DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY);
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
      '#url' => $this->displayBuildable()->getBuilderUrl(),
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

    $title = new TranslatableMarkup('Enable with profile');
    $form['display_builder_wrapper'][DisplayBuildableInterface::PROFILE_PROPERTY] = $this->displayBuildable()->buildInstanceForm(FALSE, $title, FALSE);

    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $entity */
    $entity = $this->getEntity();

    if ($entity instanceof DisplayBuilderEntityDisplayInterface) {
      $form['display_builder_wrapper'][DisplayBuildableInterface::PROFILE_PROPERTY]['override_form'] = $this->buildOverridesForm($entity);
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
  protected function buildOverridesForm(DisplayBuilderEntityDisplayInterface $entity): array {
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $overridable */
    $overridable = $entity;

    $options = $this->getSourceFieldAsOptions();
    $overrideFieldIsSet = $overridable->getDisplayBuilderOverrideField();

    $form = [
      'override' => [
        '#type' => 'container',
        '#title' => $this->t('Content Override'),
        '#states' => [
          'visible' => [
            ':input[name="profile"]' => ['!value' => ''],
          ],
        ],
      ],
    ];

    $form['override']['override_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable content overrides'),
      '#description' => $this->t('Each content will have the option to define a custom display.'),
      '#default_value' => $overrideFieldIsSet ? TRUE : FALSE,
    ];

    $form['override']['settings'] = [
      '#type' => 'container',
      '#title' => $this->t('Content Override'),
      '#states' => [
        'visible' => [
          ':input[name="override_status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['override']['settings'][DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY] = [
      '#type' => 'select',
      '#title' => $this->t('Profile for overrides'),
      '#description' => $this->t('The profile used for content overrides. Can be changed at any time.'),
      '#options' => $this->displayBuildable()->getAllowedProfiles(),
      '#default_value' => $overridable->getDisplayBuilderOverrideProfile()?->id() ?? 'default',
    ];

    if (!empty($options)) {
      $form['override']['settings'][DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY] = [
        '#type' => 'select',
        '#title' => $this->t('Display Override Field'),
        '#description' => $this->t('The field where per-content display overrides are stored.'),
        '#options' => $options,
        '#default_value' => $overrideFieldIsSet,
      ];
    }

    if (!$this->displayBuildable()->isAllowed()) {
      $form['override']['override_status']['#disabled'] = TRUE;
      $form['override']['settings'][DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY]['#disabled'] = TRUE;
      $form['override']['settings'][DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY]['#disabled'] = TRUE;
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
      if ($display instanceof DisplayBuilderEntityDisplayInterface) {
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
    $fields = [];
    // Load field instance definitions.
    $field_storage_definitions = $this->entityFieldManager->getFieldDefinitions(
      $display->getTargetEntityTypeId(),
      $display->getTargetBundle(),
    );

    if ($display instanceof DisplayBuilderEntityDisplayInterface) {
      $already_mapped = $this->getAlreadyMappedFields($display);

      foreach ($field_storage_definitions as $field_name => $field_definition) {
        if ($field_definition->getType() !== 'ui_patterns_source') {
          continue;
        }

        if (\in_array($field_name, $already_mapped, TRUE)) {
          continue;
        }
        $field = FieldConfig::loadByName($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle(), $field_name);
        $fields[$field_name] = $field ? $field->label() : $field_name;
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

  /**
   * Gets the display buildable manager.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The manager for display buildable.
   */
  protected function displayBuildable(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('entity_view', ['entity' => $this->getEntity()]);

    return $buildable;
  }

  /**
   * Create a field based on a name.
   *
   * @param string $field_name
   *   The field name, field prefix will be added.
   */
  private function createOverrideField(string $field_name): string {
    try {
      $field_prefix = $this->configFactory()->get('field_ui.settings')->get('field_prefix');
    }
    catch (\Throwable $th) {
      $field_prefix = 'field_';
    }

    $field_name = $field_prefix . $field_name;

    if (\strlen($field_name) > FieldStorageConfig::NAME_MAX_LENGTH) {
      $field_name = \substr($field_name, 0, FieldStorageConfig::NAME_MAX_LENGTH);
    }
    $field_storage = FieldStorageConfig::loadByName($this->entity->getTargetEntityTypeId(), $field_name);

    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $this->entity->getTargetEntityTypeId(),
        'type' => 'ui_patterns_source',
      ]);
      $field_storage->setTranslatable(TRUE);
      $field_storage->setCardinality(-1);
      $field_storage->save();
    }

    // Add the field prefix to the field name and cut to max size if needed.
    $field_definition = FieldConfig::loadByName($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle(), $field_name);

    if (!$field_definition) {
      $view_mode_id = $this->entity->getMode();
      $view_mode_id = ($view_mode_id === 'default') ? 'full' : $view_mode_id;
      $view_mode_id = \sprintf('%s.%s', $this->entity->getTargetEntityTypeId(), $view_mode_id);
      $view_mode = $this->entityTypeManager->getStorage('entity_view_mode')->load($view_mode_id);
      $field_definition = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $this->entity->getTargetBundle(),
        'field_name' => $field_name,
        'label' => $this->t('@display display override', ['@display' => $view_mode->label()]),
      ]);
      $field_definition->setTranslatable(TRUE);
      $field_definition->save();
    }

    return $field_name;
  }

  /**
   * Set the lock status of the override field if it exists.
   *
   * @param bool $locked
   *   Whether to lock or unlock the field.
   */
  private function setOverrideFieldLocked(bool $locked): void {
    $field_name = $this->entity->getDisplayBuilderOverrideField();

    if (!$field_name) {
      return;
    }
    $field_storage = FieldStorageConfig::loadByName($this->entity->getTargetEntityTypeId(), $field_name);

    if ($field_storage) {
      $field_storage->setLocked($locked);
      $field_storage->save();
    }
  }

}
