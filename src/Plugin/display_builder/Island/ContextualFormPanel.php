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
use Drupal\ui_patterns\PropTypePluginManager;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Instance form island plugin implementation.
 */
#[Island(
  id: 'contextual_form',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Contextual form'),
  description: new TranslatableMarkup('Configure the active component or block.'),
  type: IslandType::Contextual,
  theme: 'admin',
)]
class ContextualFormPanel extends IslandPluginBase implements IslandWithFormInterface {

  use IslandWithFormTrait;

  /**
   * The prop type plugin manager.
   */
  protected PropTypePluginManager $propTypeManager;

  /**
   * The UI Patterns source plugin manager.
   */
  protected SourcePluginManager $sourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->propTypeManager = $container->get('plugin.manager.ui_patterns_prop_type');
    $instance->sourceManager = $container->get('plugin.manager.ui_patterns_source');

    return $instance;
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

      $this->alterFormValues($form_state);
      $source = $this->sourceManager->getSource($this->data['node_id'], [], $this->data, $contexts);
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
    return $this->reloadWithLocalData($builder_id, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    // Reload the form itself on update.
    // @todo pass \Drupal\display_builder\InstanceInterface object in
    // parameters instead of loading again.
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
    $data = $builder->get($instance_id);

    return $this->reloadWithLocalData($builder_id, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithLocalData($builder_id, []);
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

    $form = \array_merge(
      ['info' => $this->getComponentMetadata($component_id)],
      $form
    );
  }

  /**
   * Get component metadata.
   *
   * @param string $component_id
   *   The component ID.
   *
   * @return array
   *   A renderable array.
   */
  protected function getComponentMetadata(string $component_id): array {
    $component = $this->sdcManager->find($component_id);
    $build = [];

    if ($description = $component->metadata->description) {
      $description = [
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $description,
          '#attributes' => [
            'class' => ['description'],
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'sl-button',
          '#value' => new TranslatableMarkup('Hide description'),
          '#attributes' => [
            'size' => 'small',
            'variant' => 'default',
            'class' => ['db-description-toggle'],
          ],
        ],
      ];
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        'content' => $description,
        '#attributes' => [
          // Important for description toggle.
          // @see assets/js/form_description.js
          'class' => ['db-instance-description'],
        ],
      ];
    }

    return $build;
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
