<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * State buttons island plugin implementation.
 */
#[Island(
  id: 'state',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('State'),
  description: new TranslatableMarkup('Publish and reset the display.'),
  type: IslandType::Button,
  default_region: 'end',
)]
class StateButtons extends IslandPluginToolbarButtonConfigurationBase {

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    if (!$builder->canSaveContextsRequirement()) {
      return [];
    }

    $buttons = $this->buildStateButtons($builder);

    if (empty($buttons)) {
      return [];
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => $buttons,
      ],
    ];
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
   * Build state buttons.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The current display builder instance.
   *
   * @return array
   *   A renderable array of buttons.
   */
  protected function buildStateButtons(InstanceInterface $instance): array {
    $instance_d = (string) $instance->id();
    $buttons = [];
    $hasSave = $instance->hasSave();
    $saveIsCurrent = $hasSave ? $instance->saveIsCurrent() : FALSE;

    if ($this->isButtonEnabled('publish') && !$saveIsCurrent) {
      $buttons[] = $this->htmxEvents->onSave($this->buildPublishButton(), $instance_d);
    }

    if ($this->isButtonEnabled('restore') && !$saveIsCurrent) {
      $buttons[] = $this->htmxEvents->onReset($this->buildRestoreButton(), $instance_d);
    }

    if ($this->isButtonEnabled('revert') && $this->isOverridden($instance_d)) {
      $buttons[] = $this->htmxEvents->onRevert($this->buildRevertButton(), $instance_d);
    }

    return $buttons;
  }

  /**
   * {@inheritdoc}
   */
  protected function hasButtons(): array {
    return [
      'publish' => [
        'title' => $this->t('Publish'),
        'default' => 'label',
      ],
      'restore' => [
        'title' => $this->t('Restore'),
        'default' => 'icon',
      ],
      'revert' => [
        'title' => $this->t('Revert'),
        'default' => 'icon',
      ],
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
   * Builds the publish button.
   *
   * @return array
   *   The publish button render array.
   */
  private function buildPublishButton(): array {
    $button = $this->buildButton(
      $this->showLabel('publish') ? $this->t('Publish') : '',
      'publish',
      $this->showIcon('publish') ? 'upload' : '',
      $this->t('Publish this display in current state. (shortcut: P)'), ['P' => $this->t('Publish this display (shift+P)')]
    );
    $button['#props']['variant'] = 'primary';
    $button['#attributes']['outline'] = TRUE;

    return $button;
  }

  /**
   * Builds the restore button.
   *
   * @return array
   *   The restore button render array.
   */
  private function buildRestoreButton(): array {
    $button = $this->buildButton(
      $this->showLabel('restore') ? $this->t('Restore') : '',
      'restore',
      $this->showIcon('restore') ? 'arrow-repeat' : '',
      $this->t('Restore to last saved version')
    );
    $button['#props']['variant'] = 'warning';
    $button['#attributes']['outline'] = TRUE;

    return $button;
  }

  /**
   * Builds the revert button.
   *
   * @return array
   *   The revert button render array.
   */
  private function buildRevertButton(): array {
    $button = $this->buildButton(
      $this->showLabel('revert') ? $this->t('Revert') : '',
      'revert',
      $this->showIcon('revert') ? 'box-arrow-in-down' : '',
      $this->t('Revert to default display (not overridden)')
    );
    $button['#props']['variant'] = 'danger';
    $button['#attributes']['outline'] = TRUE;

    return $button;
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
    if (!$this->builder) {
      // @todo pass \Drupal\display_builder\InstanceInterface object in
      // parameters instead of loading again.
      /** @var \Drupal\display_builder\InstanceStorage $storage */
      $storage = $this->entityTypeManager->getStorage('display_builder_instance');
      /** @var \Drupal\display_builder\InstanceInterface $builder */
      $builder = $storage->load($builder_id);
      $this->builder = $builder;
    }

    return $this->addOutOfBand(
      $this->build($this->builder),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

}
