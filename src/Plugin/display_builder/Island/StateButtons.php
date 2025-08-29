<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * State buttons island plugin implementation.
 */
#[Island(
  id: 'state',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('State'),
  description: new TranslatableMarkup('Buttons to publish and reset the display.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'S' => new TranslatableMarkup('(shift+s) Save this display builder'),
  ],
)]
class StateButtons extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ComponentPluginManager $sdcManager,
    HtmxEvents $htmxEvents,
    StateManagerInterface $stateManager,
    EventSubscriberInterface $eventSubscriber,
    SourcePluginManager $sourceManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $sdcManager, $htmxEvents, $stateManager, $eventSubscriber, $sourceManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.sdc'),
      $container->get('display_builder.htmx_events'),
      $container->get('display_builder.state_manager'),
      $container->get('display_builder.event_subscriber'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    if (!$this->stateManager->canSaveContextsRequirement($builder_id)) {
      return [];
    }

    $buttonGroup = [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [],
      ],
    ];

    $hasSave = $this->stateManager->hasSave($builder_id);
    $saveIsCurrent = $hasSave ? $this->stateManager->saveIsCurrent($builder_id) : FALSE;

    if (!$saveIsCurrent) {
      $save = $this->buildButton($this->t('Save'), $this->t('Save this display'), 'S', FALSE);
      $save['#props']['variant'] = 'primary';
      $save['#attributes']['outline'] = TRUE;
      $buttonGroup['#slots']['buttons'][] = $this->htmxEvents->onSave($save, $builder_id);

      $restore = $this->buildButton($this->t('Restore'), $this->t('Restore to last saved version'), 'R');
      $restore['#props']['variant'] = 'warning';
      $restore['#attributes']['outline'] = TRUE;
      $buttonGroup['#slots']['buttons'][] = $this->htmxEvents->onReset($restore, $builder_id);
    }

    if ($this->isOverridden($builder_id)) {
      $revert = $this->buildButton($this->t('Revert'), $this->t('Revert to default display'));
      $revert['#props']['variant'] = 'danger';
      $revert['#attributes']['outline'] = TRUE;
      $buttonGroup['#slots']['buttons'][] = $this->htmxEvents->onRevert($revert, $builder_id);
    }

    return $buttonGroup;
  }

  /**
   * {@inheritdoc}
   */
  public function onSave(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id, ?string $current_island_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->rebuild($builder_id);
  }

  /**
   * Check if the display builder is on an entity override.
   *
   * @param string $builder_id
   *   The ID of the builder.
   *
   * @return bool
   *   Returns TRUE if the display builder is on an entity override.
   */
  protected function isOverridden(string $builder_id): bool {
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

    $overriddenField = $entity->get($instanceInfos['field_name']);

    if ($overriddenField->isEmpty()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Rebuilds the island with the given builder ID.
   *
   * @param string $builder_id
   *   The ID of the builder.
   *
   * @return array
   *   The rebuilt island.
   */
  private function rebuild(string $builder_id): array {
    return $this->addOutOfBand(
      $this->build($builder_id, []),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
