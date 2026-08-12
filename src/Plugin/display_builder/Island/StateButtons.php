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
 *
 * A split button: Publish is always the visible half, and only turns
 * disabled once the published version is the current one, so its position
 * never shifts under the pointer. The other half is a caret opening
 * Restore and Revert, which stays usable while Publish is disabled -
 * reverting an override is exactly something you do on a published
 * display. It follows the same rule the other way round: when neither
 * action applies it turns disabled rather than disappearing. Shoelace has
 * no split button component: it is a button group holding a button and a
 * dropdown, as its own documentation suggests.
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

    if ($this->isButtonEnabled('publish')) {
      $buttons[] = $this->htmxEvents->onPublish($this->buildPublishButton($saveIsCurrent), $instance_d);
    }

    $hasRestore = $this->isButtonEnabled('restore');
    $hasRevert = $this->isButtonEnabled('revert');
    $items = [];

    if ($hasRestore && !$saveIsCurrent) {
      $items[] = $this->htmxEvents->onReset($this->buildRestoreItem(), $instance_d);
    }

    if ($hasRevert && $this->isOverridden($instance_d)) {
      $items[] = $this->htmxEvents->onRevert($this->buildRevertItem(), $instance_d);
    }

    // Availability changes with the state, the profile configuration does
    // not: the caret is there whenever either action is turned on, so the
    // group keeps one shape and only its two halves gray out.
    if ($hasRestore || $hasRevert) {
      $buttons[] = $this->buildStateDropdown($items);
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
        'default' => 'label',
        'options' => ['label', 'hidden'],
        'description' => $this->t('Shown in the dropdown next to Publish, which is too narrow to read an icon on its own.'),
      ],
      'revert' => [
        'title' => $this->t('Revert'),
        'default' => 'label',
        'options' => ['label', 'hidden'],
        'description' => $this->t('Shown in the dropdown next to Publish, which is too narrow to read an icon on its own.'),
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
   * @param bool $published
   *   Whether the published version is already the current one.
   *
   * @return array
   *   The publish button render array.
   */
  private function buildPublishButton(bool $published): array {
    $button = $this->buildButton(
      $this->showLabel('publish') ? $this->t('Publish') : '',
      'publish',
      $this->showIcon('publish') ? 'upload' : '',
      $published ? $this->t('This display is already published in its current state.') : $this->t('Publish this display in current state. (shortcut: Shift+P)'),
      // No shortcut while there is nothing to publish: keyboard.js works by
      // clicking the element, and the help overlay would otherwise advertise
      // a key that does nothing.
      $published ? NULL : ['shift+p' => $this->t('Publish this display')]
    );
    $button['#props']['variant'] = 'primary';
    $button['#attributes']['outline'] = TRUE;

    if ($published) {
      $button['#attributes']['disabled'] = TRUE;
    }

    return $button;
  }

  /**
   * Builds the dropdown holding the secondary state actions.
   *
   * @param array $items
   *   The menu item render arrays to offer, empty when neither action
   *   applies to the current state.
   *
   * @return array
   *   The dropdown render array.
   */
  private function buildStateDropdown(array $items): array {
    // No label and no icon: the caret the dropdown component puts on its
    // trigger is the whole button. sl-button forwards its title to the
    // <button> in its shadow root, which is what takes focus, so that
    // title is also the accessible name - there is no slotted icon here to
    // carry one. @see components/shoelace/button/button.twig.
    $trigger = $this->buildButton('', 'state_menu');
    $trigger['#props']['variant'] = 'primary';
    $trigger['#attributes']['outline'] = TRUE;
    $trigger['#attributes']['title'] = empty($items)
      ? $this->t('No other publishing action available in this state.')
      : $this->t('More publishing actions');

    if (empty($items)) {
      $trigger['#attributes']['disabled'] = TRUE;
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#props' => [
        // The island sits at the end of the toolbar: an end-aligned menu
        // stays inside the viewport.
        'placement' => 'bottom-end',
      ],
      '#slots' => [
        'button' => $trigger,
        'content' => [
          '#type' => 'component',
          '#component' => 'display_builder:menu',
          '#slots' => [
            'content' => $items,
          ],
          '#attributes' => [
            'class' => ['db-background', 'db-state-menu'],
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the restore menu item.
   *
   * @return array
   *   The restore menu item render array.
   */
  private function buildRestoreItem(): array {
    return $this->buildStateItem(
      $this->t('Restore'),
      'restore',
      $this->t('Restore to last saved version'),
    );
  }

  /**
   * Builds the revert menu item.
   *
   * @return array
   *   The revert menu item render array.
   */
  private function buildRevertItem(): array {
    return $this->buildStateItem(
      $this->t('Revert'),
      'revert',
      $this->t('Revert to default display (not overridden)'),
    );
  }

  /**
   * Builds a menu item for a secondary state action.
   *
   * How destructive the action is comes from the item text color, keyed on
   * its value. @see components/toolbar/toolbar.css.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The item title, always displayed: an item is the only thing naming
   *   its action here, unlike a button it cannot fall back on a tooltip.
   * @param string $action
   *   The action ID, also used as menu item value and as test ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $tooltip
   *   The native tooltip describing the action.
   *
   * @return array
   *   The menu item render array.
   */
  private function buildStateItem(TranslatableMarkup $title, string $action, TranslatableMarkup $tooltip): array {
    $item = $this->buildMenuItem($title, $action);
    $item['#attributes']['title'] = $tooltip;
    $item['#attributes']['data-island-action'] = $action;
    $item['#attributes']['data-testid'] = $action;

    return $item;
  }

}
