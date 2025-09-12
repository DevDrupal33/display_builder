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
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
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
    protected EntityTypeManagerInterface $entityTypeManager,
    EventSubscriberInterface $eventSubscriber,
    SourcePluginManager $sourceManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $sdcManager, $htmxEvents, $entityTypeManager, $eventSubscriber, $sourceManager);
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
      $container->get('entity_type.manager'),
      $container->get('display_builder.event_subscriber'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $builder_id = (string) $builder->id();

    if (!$builder->canSaveContextsRequirement()) {
      return [];
    }

    $buttonGroup = [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [],
      ],
    ];

    $hasSave = $builder->hasSave();
    $saveIsCurrent = $hasSave ? $builder->saveIsCurrent() : FALSE;

    if (!$saveIsCurrent) {
      $save = $this->buildButton('', 'save', 'floppy', $this->t('Save this display in current state, this will publish your display. (shortcut: S)'), ['S' => $this->t('Save this display (shift+S)')]);
      $save['#props']['variant'] = 'primary';
      $save['#attributes']['outline'] = TRUE;
      $buttonGroup['#slots']['buttons'][] = $this->htmxEvents->onSave($save, $builder_id);

      $restore = $this->buildButton('', 'restore', 'arrow-repeat', $this->t('Restore to last saved version'));
      $restore['#props']['variant'] = 'warning';
      $restore['#attributes']['outline'] = TRUE;
      $buttonGroup['#slots']['buttons'][] = $this->htmxEvents->onReset($restore, $builder_id);
    }

    if ($this->isOverridden($builder_id)) {
      $save = $this->buildButton('', 'revert', 'box-arrow-in-down', $this->t('Revert to default display (not overridden)'));
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
  public function onUpdate(string $builder_id, string $instance_id): array {
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

    // Do not get the profile entity ID from Instance context because the
    // data stored there is not reliable yet.
    // See: https://www.drupal.org/project/display_builder/issues/3544545
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
    // @todo pass \Drupal\display_builder\InstanceInterface object in
    // parameters instead of loading again.
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);

    return $this->addOutOfBand(
      $this->build($builder, []),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
