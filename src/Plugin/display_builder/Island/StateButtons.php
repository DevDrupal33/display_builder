<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;

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
  region: 'end',
)]
class StateButtons extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;
  use IslandReloadEventsTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['revert' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['revert'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Offer revert'),
      '#description' => $this->t('Revert drops the override and puts the display back on its default. A power user action: turn it off on profiles where losing an override by accident is worse than having to rebuild it.'),
      '#default_value' => $this->offersRevert(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    return [
      $this->offersRevert()
        ? $this->t('Publish, Restore and Revert.')
        : $this->t('Publish and Restore.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => $this->buildStateButtons($builder),
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
    $instance_id = (string) $instance->id();
    $saveIsCurrent = $instance->isPublishedPresent();
    $buttons = [
      $this->htmxEvents->onPublish($this->buildPublishButton($saveIsCurrent), $instance_id),
    ];
    $items = [];

    if ($instance->isPublished() && !$saveIsCurrent) {
      $items[] = $this->htmxEvents->onReset($this->buildRestoreItem(), $instance_id);
    }

    if ($this->offersRevert() && $this->isOverridden($instance)) {
      $items[] = $this->htmxEvents->onRevert($this->buildRevertItem(), $instance_id);
    }

    // Availability changes with the state, the profile configuration does
    // not: the caret is always there, so the group keeps one shape and only
    // its two halves gray out.
    $buttons[] = $this->buildStateDropdown($items);

    return $buttons;
  }

  /**
   * Whether the profile offers the revert action.
   *
   * @return bool
   *   TRUE when Revert is listed in the dropdown, FALSE otherwise.
   */
  protected function offersRevert(): bool {
    return (bool) ($this->getConfiguration()['revert'] ?? TRUE);
  }

  /**
   * Check if the display builder is on an entity override.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The current display builder instance.
   *
   * @return bool
   *   Returns TRUE if the display builder is on an entity override.
   */
  protected function isOverridden(InstanceInterface $instance): bool {
    $buildable = $instance->getBuildablePlugin();

    if ($buildable instanceof DisplayBuildableOverrideInterface) {
      return !empty($buildable->getSources());
    }

    return FALSE;
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
      $this->t('Publish'),
      'publish',
      NULL,
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
