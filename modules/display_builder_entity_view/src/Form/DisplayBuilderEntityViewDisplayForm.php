<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Component\Plugin\PluginManagerBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\StorageProperties;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplayStorage;
use Drupal\layout_builder\Form\LayoutBuilderEntityViewDisplayForm;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The config form builder for Display Builder.
   */
  protected ConfigFormBuilderInterface $configFormBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    FieldTypePluginManagerInterface $field_type_manager,
    PluginManagerBase $plugin_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFormBuilderInterface $config_form_builder,
  ) {
    parent::__construct($field_type_manager, $plugin_manager, $entity_display_repository, $entity_field_manager);
    $this->configFormBuilder = $config_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager'),
      $container->get('display_builder.config_form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, mixed $source_storage = NULL): array {
    $this->sourceStorage = $source_storage;

    return parent::buildForm($form, $form_state, $section_storage);
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
      '#url' => $this->entity->getBuilderUrl(),
      '#access' => $is_display_builder_enabled,
    ];

    if (isset($form['modes'])) {
      $form['modes']['#weight'] = 0;
    }

    if (isset($form['layout'])) {
      $form['layout']['#weight'] = 2;
      $form['layout']['#open'] = $is_layout_builder_enabled;
    }

    $form['wrapper'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Display builder'),
      '#weight' => 1,
    ];

    $form['wrapper'][StorageProperties::ConfigEntityId->value] = $this->configFormBuilder->build($this->entity, FALSE);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    // @todo we should have always a fallback.
    $display_builder_config = $form_state->getValue([StorageProperties::ConfigEntityId->value]) ?? 'default';
    // Empty mean disabled.
    if (empty($display_builder_config)) {
      $this->entity->unsetThirdPartySetting('display_builder', StorageProperties::ConfigEntityId->value);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', StorageProperties::ConfigEntityId->value, $display_builder_config);
    }

    $this->entity->save();
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

}
