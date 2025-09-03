<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\display_builder\Island;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandPluginFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content edit island plugin implementation.
 */
#[Island(
  id: 'content_edit',
  label: new TranslatableMarkup('Content edit'),
  description: new TranslatableMarkup('Allow to edit a content in the display.'),
  type: IslandType::View,
  icon: 'pencil-square',
)]
class ContentEditPanel extends IslandPluginBase implements IslandWithFormInterface, PluginFormInterface {

  use IslandPluginConfigurationFormTrait;
  use IslandPluginFormTrait;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The content entity.
   */
  protected ?FieldableEntityInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    // If (!parent::isApplicable()) {
    //      return FALSE;
    //    }.
    $builder_id = $this->builderId;

    if ($this->isOverride($builder_id)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'form_mode' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();
    /** @var \Drupal\Core\Entity\Entity\EntityFormMode[] $formModes */
    $formModes = $this->entityTypeManager->getStorage('entity_form_mode')
      ->loadMultiple();
    $options = [];

    foreach ($formModes as $formMode) {
      $options[$formMode->id()] = $formMode->label();
    }

    // @todo decline per entity type, per bundle.
    // @todo or just load and loop on entity_form_display entities.
    $form['form_mode'] = [
      '#title' => $this->t('Form mode'),
      '#type' => 'select',
      '#default_value' => $configuration['form_mode'],
      '#options' => $options,
      '#empty_option' => $this->t('Default'),
      '#empty_value' => 'default',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    // @todo get the form mode label.
    return [
      $this->t('Form mode: @form_mode', [
        '@form_mode' => $configuration['form_mode'],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $contexts = $form_state->getBuildInfo()['args'][1];
    $entity = $contexts['entity']->getContextValue();
    // Problem of using entity.form_builder is that it adds process callbacks
    // expecting that the form class is an EntityForm class.
    //    $form = NestedArray::mergeDeep($form, \Drupal::service('entity.form_builder')
    //      ->getForm($entity, 'default'));.
    $form['toto'] = [
      '#type' => 'inline_entity_form',
      '#entity_type' => 'node',
      '#bundle' => 'article',
      // '#langcode' => $langcode,
      '#default_value' => $entity,
      '#op' => 'default',
      '#form_mode' => 'default',
      '#save_entity' => TRUE,
      // '#ief_row_delta' => $delta,
      //      // Used by Field API and controller methods to find the relevant
      //      // values in $form_state.
      //      '#parents' => $parents,
      //      // Labels could be overridden in field widget settings. We won't have
      //      // access to those in static callbacks (#process, ...) so let's add
      //      // them here.
      //      '#ief_labels' => $this->getEntityTypeLabels(),
      //      // Identifies the IEF widget to which the form belongs.
      //      '#ief_id' => $this->getIefId(),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
  }

  /**
   * Check if the display builder is on an entity override.
   *
   * @param string $builder_id
   *   The ID of the builder.
   *
   * @return bool
   *   Returns TRUE if the display builder is on an entity override.
   *
   * @see StateButtons::isOverridden()
   */
  protected function isOverride(string $builder_id): bool {
    if (!$this->moduleHandler->moduleExists('display_builder_entity_view')) {
      return FALSE;
    }

    $instanceInfos = DisplayBuilderItemList::checkInstanceId($builder_id);

    if (!isset($instanceInfos['entity_type_id'], $instanceInfos['entity_id'], $instanceInfos['field_name'])) {
      return FALSE;
    }

    // Do not use the entity from the state manager builder context because
    // the fields are empty.
    $entity = $this->entityTypeManager->getStorage($instanceInfos['entity_type_id'])
      ->load($instanceInfos['entity_id']);

    if (!($entity instanceof FieldableEntityInterface)) {
      return FALSE;
    }

    $this->entity = $entity;

    return TRUE;
  }

}
