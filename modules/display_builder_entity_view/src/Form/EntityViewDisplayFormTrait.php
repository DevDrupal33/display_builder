<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common methods for entity view display form.
 */
trait EntityViewDisplayFormTrait {

  /**
   * The config form builder for Display Builder.
   */
  protected ConfigFormBuilderInterface $configFormBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->configFormBuilder = $container->get('display_builder.config_form_builder');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);

    // @todo we should have always a fallback.
    $display_builder_config = $formState->getValue([ConfigFormBuilderInterface::PROFILE_PROPERTY]) ?? 'default';

    // Empty mean disabled.
    if (empty($display_builder_config)) {
      $this->entity->unsetThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, $display_builder_config);
    }

    $this->entity->save();
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

    return $form;
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
