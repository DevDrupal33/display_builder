<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\display_builder\Island;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
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
class ContentEditPanel extends IslandPluginBase implements IslandWithFormInterface {

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
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $contexts = $form_state->getBuildInfo()['args'][1];
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $contexts['entity']->getContextValue();
    // Problem of using entity.form_builder is that it adds process callbacks
    // expecting that the form class is an EntityForm class.
    $url = Url::fromRoute('display_builder.api_content_edit', ['builder' => $this->builderId]);
    $form['content'] = [
      '#type' => 'inline_entity_form',
      '#entity_type' => $entity->getEntityTypeId(),
      '#bundle' => $entity->bundle(),
      '#default_value' => $entity,
      '#op' => 'default',
      '#form_mode' => 'default',
      // @todo Move this to \Drupal\display_builder\HtmxEvents.
      '#attributes' => [
        'hx-put' => $url->toString(),
        'hx-trigger' => 'change consume',
        'hx-swap' => 'none',
      ],
    ];
    $form['entity_type'] = [
      '#type' => 'hidden',
      '#value' => $entity->getEntityTypeId(),
    ];
    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $entity->id(),
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
