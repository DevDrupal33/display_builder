<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
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

  use IslandReloadEventsTrait;

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
    if (!$builder->isPublishable()) {
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
  public function onPublish(InstanceInterface $instance): array {
    return $this->reloadWithGlobalData($instance);
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
    $saveIsCurrent = $instance->isPublishedPresent();

    if ($this->isButtonEnabled('publish') && !$saveIsCurrent) {
      $buttons[] = $this->htmxEvents->onPublish($this->buildPublishButton(), $instance_d);
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

    $instanceInfos = EntityViewOverride::checkInstanceId($builder_id);

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
      $this->t('Publish this display in current state. (shortcut: Shift+P)'), ['shift+p' => $this->t('Publish this display')]
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

}
