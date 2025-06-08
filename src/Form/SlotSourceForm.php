<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for slot sources.
 */
final class SlotSourceForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    protected SourcePluginManager $sourceManager,
    protected PluginManagerInterface $propTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('plugin.manager.ui_patterns_prop_type'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_slot_source';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $data = $form_state->getBuildInfo()['args'][0];
    $contexts = $form_state->getBuildInfo()['args'][1] ?? [];

    if (!isset($data['source_id'])) {
      return [];
    }

    if (!isset($data['_instance_id'])) {
      throw new \Exception('No instance ID provided.');
    }

    $this->alterFormValues($form_state, $data);
    $source = $this->sourceManager->getSource($data['_instance_id'], [], $data, $contexts);
    $form = $source ? $source->settingsForm([], $form_state) : [];

    if ($this->isMultipleItemsSlotSource($data['source'])) {
      $form = $this->removeItemSelector($form);
    }

    $component_id = ($data['source_id'] === 'component') ? $data['source']['component']['component_id'] ?? NULL : NULL;
    if ($component_id && ($data['source_id'] === 'component')) {
      $this->alterFormForComponent($form, $component_id);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Alter the form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $data
   *   The data to inject in source plugin.
   */
  protected function alterFormValues(FormStateInterface $form_state, array &$data): void {
    // When this is an Ajax CALL, we directly inject the data into the source
    // settings, but not during the rebuilt.
    $values = $form_state->getValues();
    if (isset($values['_drupal_ajax']) && $values['_drupal_ajax'] && !$form_state->isRebuilding()) {
      if ($data['source_id'] !== 'component') {
        $data['source'] = $values;
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
      $data['source'] = $values;
    }
  }

  /**
   * Alter the form in a case of a component.
   *
   * @param array $form
   *   The form.
   * @param string|null $component_id
   *   The component ID.
   */
  protected function alterFormForComponent(array &$form, ?string $component_id): void {
    if (!$component_id) {
      return;
    }

    if (!isset($form['component']['component_id'])) {
      $form['component']['component_id'] = [
        '#type' => 'hidden',
        '#value' => $component_id,
      ];
    }
    $form['component']['#render_slots'] = FALSE;
    $form['component']['#component_id'] = $component_id;
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
  protected function isMultipleItemsSlotSource(array $data): bool {
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
  protected function removeItemSelector(array $form): array {
    $form['plugin_id']['#type'] = 'hidden';
    unset($form['plugin_id']['#options']);

    return $form;
  }

}
