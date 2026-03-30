<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\IslandWithFormTrait;
use Drupal\display_builder\SourceWithSlotsInterface;

/**
 * Instance form island plugin implementation.
 */
#[Island(
  id: 'contextual_form',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Config'),
  description: new TranslatableMarkup('Configure the active component or block.'),
  type: IslandType::Contextual,
)]
class ContextualFormPanel extends IslandPluginBase implements IslandWithFormInterface {

  use IslandWithFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    try {
      $contexts = $form_state->getBuildInfo()['args'][1] ?? [];

      $this->alterFormValues($form_state);
      $source = $this->sourceManager->getSource($this->data['node_id'], [], $this->data, $contexts);

      if ($source instanceof SourceWithSlotsInterface) {
        $form = $source->settingsFormPropsOnly([], $form_state);
      }
      else {
        $form = $source ? $source->settingsForm([], $form_state) : [];
      }

      if ($this->isMultipleItemsSlotSource($this->data['source'])) {
        $form = $this->removeItemSelector($form);
      }
    }
    catch (\Exception) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $build = parent::build($builder, $data, $options);

    if (empty($build)) {
      return $build;
    }

    if (self::isEmpty($build)) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No configuration required.'),
        '#attributes' => [
          'class' => ['description'],
        ],
      ];
    }

    $build = [
      'source' => $build,
    ];

    $build['update'] = [
      '#type' => 'button',
      '#value' => 'Update',
      '#submit_button' => FALSE,
      '#attributes' => [
        'type' => 'button',
        'data-wysiwyg-fix' => TRUE,
      ],
    ];

    $build = $this->htmxEvents->onInstanceFormChange($build, $this->builderId, $this->getPluginId(), $this->data['node_id']);

    return $this->htmxEvents->onInstanceUpdateButtonClick($build, $this->builderId, $this->getPluginId(), $this->data['node_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array {
    return $this->reloadWithNodeData($instance, $node_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array {
    return $this->reloadWithNodeData($instance, $node_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(InstanceInterface $instance, array $data): array {
    return $this->reloadWithLocalData($instance, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array {
    // Reload the form itself on update.
    $data = $instance->getNode($node_id);

    return $this->reloadWithLocalData($instance, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array {
    return $this->reloadWithLocalData($instance, []);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    return isset($this->data['source_id']) && isset($this->data['node_id']);
  }

  /**
   * Alter the form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function alterFormValues(FormStateInterface $form_state): void {
    // When this is an Ajax CALL, we directly inject the data into the source
    // settings, but not during the rebuilt.
    $values = $form_state->getValues();

    if (isset($values['_drupal_ajax']) && $values['_drupal_ajax'] && !$form_state->isRebuilding()) {
      if ($this->data['source_id'] !== 'component') {
        $this->data['source'] = $values;
      }
    }

    // When rebuilding the form, we need to inject the values into the source
    // settings.
    if ($form_state->isRebuilding()) {
      // Allow to get the posted values through ajax, and give them to the
      // source plugin through its settings (essential).
      if (isset($values['source'])) {
        unset($values['source']);
      }

      if (!empty($values)) {
        $this->data['source'] = $values;
      }
    }
  }

  /**
   * Indicates whether the given form array is empty.
   *
   * @param array $form
   *   The form.
   *
   * @return bool
   *   Whether the given element is empty.
   */
  private static function isEmpty(array $form) {
    $keys = Element::children($form);

    // Quick valid if a component. An empty component is a rare occurrence.
    if (isset($keys['component'])) {
      return FALSE;
    }

    return \array_diff(Element::children($form), [
      'plugin_id',
      'form_build_id',
      'form_token',
      'form_id',
      // Exclude some core block with no configuration.
      // @todo remove when we do not need the update button anymore.
      'help_block',
      'local_actions_block',
      'node_syndicate_block',
      'system_breadcrumb_block',
      'system_clear_cache_block',
      'system_messages_block',
      'system_powered_by_block',
    ]) === [];
  }

  /**
   * Has the slot source multiple items?
   *
   * Some slot sources have 'multiple' items, with a select form element first,
   * then an item specific form changing with Ajax. They have both a plugin_id
   * key and a dynamic key with the value of the plugin_id.
   *
   * @param array $data
   *   The slot source data containing:
   *   - plugin_id: The plugin ID.
   *
   * @return bool
   *   Is multiple or not.
   */
  private function isMultipleItemsSlotSource(array $data): bool {
    if (!isset($data['plugin_id']) || !\is_string($data['plugin_id'])) {
      return FALSE;
    }

    if (\count($data) === 1) {
      // If there is only plugin_id, without any settings, it is OK.
      return TRUE;
    }
    // If there are settings, we need at least the one specific to the item.
    $plugin_id = (string) $data['plugin_id'];

    if (isset($data[$plugin_id]) && \is_array($data[$plugin_id])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Remove the item selector from a form.
   *
   * For multiple items slot sources, we don't want to show the item selector
   * since it is already selected in the slot configuration.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The modified form array.
   */
  private function removeItemSelector(array $form): array {
    $form['plugin_id']['#type'] = 'hidden';
    unset($form['plugin_id']['#options']);

    return $form;
  }

}
