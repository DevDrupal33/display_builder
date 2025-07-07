<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Instance form island plugin implementation.
 */
#[Island(
  id: 'instance_form',
  label: new TranslatableMarkup('Instance form'),
  description: new TranslatableMarkup('Configuration of the active element.'),
  type: IslandType::Contextual,
)]
class InstanceFormPanel extends IslandPluginBase implements IslandWithFormInterface {

  use IslandPluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $sdcManager,
    protected HtmxEvents $htmxEvents,
    protected StateManagerInterface $stateManager,
    protected EventSubscriberInterface $eventSubscriber,
    protected SourcePluginManager $sourceManager,
    protected PluginManagerInterface $propTypeManager,
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
      $container->get('plugin.manager.ui_patterns_prop_type'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    try {
      $contexts = $form_state->getBuildInfo()['args'][1] ?? [];
      $options = $form_state->getBuildInfo()['args'][2] ?? [];

      $this->alterFormValues($form_state, $options);
      $source = $this->sourceManager->getSource($this->data['_instance_id'], [], $this->data, $contexts);
      $form = $source ? $source->settingsForm([], $form_state) : [];

      if ($this->isMultipleItemsSlotSource($this->data['source'])) {
        $form = $this->removeItemSelector($form);
      }

      $component_id = ($this->data['source_id'] === 'component') ? $this->data['source']['component']['component_id'] ?? NULL : NULL;
      if ($component_id && ($this->data['source_id'] === 'component')) {
        $this->alterFormForComponent($form, $component_id);
      }
    }
    catch (\Exception) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $build = parent::build($builder_id, $data, $options);

    if (empty($build)) {
      return $build;
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

    // @todo disable auto submit for now related to # ajax error in 3531518.
    // phpcs:disable Drupal.Files.LineLength.TooLong
    // $build = $this->htmxEvents->onInstanceFormChange($build, $this->builderId, $this->data['_instance_id']);
    // phpcs:enable Drupal.Files.LineLength.TooLong
    return $this->htmxEvents->onInstanceUpdateButtonClick($build, $this->builderId, $this->data['_instance_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithInstanceData($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadWithInstanceData($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(string $builder_id, array $data): array {
    return $this->reloadWithLocalData($builder_id, $data, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, ?string $instance_id, ?string $current_island_id): array {
    // Reload the form itself on update.
    $data = $this->stateManager->get($builder_id, $instance_id);
    return $this->reloadWithLocalData($builder_id, $data, $current_island_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithLocalData($builder_id, [], NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    return isset($this->data['source_id']) && isset($this->data['_instance_id']);
  }

  /**
   * Alter the form values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $options
   *   The data to inject in source plugin.
   */
  protected function alterFormValues(FormStateInterface $form_state, array $options): void {
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
    if ($form_state->isRebuilding() && (!isset($options['current_island_id']) || $this->getPluginId() === $options['current_island_id'])) {
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
