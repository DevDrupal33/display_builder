<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Plugin\UiPatterns\Source;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_entity_form\Plugin\Derivative\FieldWidgetSourceDeriver;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\FieldValueSourceBase;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for field widget.
 */
#[Source(
  id: 'field_widget',
  label: new TranslatableMarkup('[Field] Widget'),
  description: new TranslatableMarkup('Entity Field exposed with a field widget'),
  deriver: FieldWidgetSourceDeriver::class
)]
class FieldWidgetSource extends FieldValueSourceBase implements TrustedCallbackInterface {

  /**
   * The widget plugin manager.
   */
  protected ?WidgetPluginManager $widgetPluginManager;

  /**
   * The field type plugin manager.
   */
  protected ?FieldTypePluginManagerInterface $fieldTypePluginManager;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderFormatterSettingsForm'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->widgetPluginManager = $container->get('plugin.manager.field.widget');
    $instance->fieldTypePluginManager = $container->get('plugin.manager.field.field_type');

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\ui_patterns\PluginSettingsInterface
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $this->buildFieldWidgetForm($form, $form_state);

    return $form;
  }

  /**
   * Customize slot or prop form elements (pre-render).
   *
   * @param array $element
   *   Element to process.
   *
   * @return array
   *   Processed element
   */
  public static function preRenderFormatterSettingsForm(array $element): array {
    $element['settings_wrapper']['settings'] = $element['settings'];
    $element['settings']['#printed'] = TRUE;
    $element['settings_wrapper']['third_party_settings'] = $element['third_party_settings'];
    $element['third_party_settings']['#printed'] = TRUE;

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\ui_patterns\SourceInterface
   */
  public function getPropValue(): mixed {
    /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $data */
    $data = $this->getContext('entity')->getContextData();
    $entity = $data->getEntity();
    $items = $this->getEntityFieldItemList();
    $configuration = $this->getConfiguration();
    $fieldDefinition = $this->getFieldDefinition();
    $widget = $this->createWidgetInstance($configuration['settings']['type'], $fieldDefinition);
    $form = \Drupal::service('entity.form_builder')->getForm($entity, 'default');
    $form_state = new FormState();
    $build = $widget->form($items, $form, $form_state);
    // For template_preprocess_field_multiple_value_form.
    $build['widget']['#attributes'] = [];
    // For template_preprocess_form_element.
    $build['widget']['#description_display'] = '';

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Component\Plugin\DependentPluginInterface
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $configuration = $this->getConfiguration();
    $fieldDefinition = $this->getFieldDefinition();

    if (empty($configuration['settings']['type'])) {
      return $dependencies;
    }
    $widget = NULL;

    try {
      $widget = $this->createWidgetInstance($configuration['settings']['type'], $fieldDefinition);
    }
    catch (\Throwable $exception) {
      // During install computeDependencies instance this plugin.
      // This can lead to unexpected configuration states. We can ignore it.
    }

    if (!$widget) {
      return $dependencies;
    }
    SourcePluginBase::mergeConfigDependencies($dependencies, $this->getPluginDependencies($widget));
    SourcePluginBase::mergeConfigDependencies($dependencies, ['module' => ['display_builder_entity_form']]);

    return $dependencies;
  }

  /**
   * Ajax callback for fields with AJAX callback to update form substructure.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The replaced form substructure.
   */
  public static function onWidgetTypeChange(array $form, FormStateInterface $form_state): array {
    $triggeringElement = $form_state->getTriggeringElement();

    // Dynamically return the dependent ajax for elements based on the
    // triggering element. This shouldn't be done statically because
    // settings forms may be different, e.g. for layout builder, core, ...
    if (!empty($triggeringElement['#array_parents'])) {
      $subformKeys = $triggeringElement['#array_parents'];
      // Remove the triggering element itself and add the 'settings' below key.
      \array_pop($subformKeys);
      // Return the subform:
      $subform_settings_wrapper = NestedArray::getValue($form, \array_merge($subformKeys, ['settings_wrapper']));
      $subform_settings = NestedArray::getValue($form, \array_merge($subformKeys, ['settings']));
      $subform_third_party_settings = NestedArray::getValue($form, \array_merge($subformKeys, ['third_party_settings']));

      return [
        '#prefix' => $subform_settings_wrapper['#prefix'],
        'settings' => $subform_settings,
        'third_party_settings' => $subform_third_party_settings,
        '#suffix' => $subform_settings_wrapper['#suffix'],
      ];
    }

    return [];
  }

  /**
   * Adds the formatter third party settings forms.
   *
   * @param \Drupal\Core\Field\WidgetInterface $plugin
   *   The formatter.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $form
   *   The (entire) configuration form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The formatter third party settings form.
   */
  private function thirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, array $form, FormStateInterface $form_state) {
    $settings_form = [];
    // Invoke hook_field_widget_third_party_settings_form(), keying resulting
    // subforms by module name.
    $this->moduleHandler->invokeAllWith(
      'field_widget_third_party_settings_form',
      static function (callable $hook, string $module) use (&$settings_form, $plugin, $field_definition, $form, $form_state) {
        $settings_form[$module] = $hook(
          $plugin,
          $field_definition,
          EntityDisplayBase::CUSTOM_MODE,
          $form,
          $form_state,
        );
      }
    );

    return $settings_form;
  }

  /**
   * Get all available formatters by loading available ones and filtering out.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @return string[]
   *   The field formatter labels keys by plugin ID.
   */
  private function getAvailableWidgetOptions(FieldStorageDefinitionInterface $field_storage_definition, FieldDefinitionInterface $field_definition): array {
    $widgets = $this->widgetPluginManager->getOptions($field_storage_definition->getType());
    $widget_instances = [];

    foreach ($widgets as $widget_id => $widget) {
      $widget_instances[$widget_id] = $this->createWidgetInstance($widget_id, $field_definition);
    }

    $filtered_widget_instances = $this->filterWidget($widget_instances, $field_definition);
    $options = \array_map(
      static function (WidgetInterface $widget) {
        $plugin_definition = $widget->getPluginDefinition();

        return ($plugin_definition instanceof PluginDefinitionInterface) ? $plugin_definition->id() : $plugin_definition['label'];
      }, $filtered_widget_instances
    );

    return $options;
  }

  /**
   * Callback to build field formatter form.
   *
   * @param array $form
   *   The source plugin settings form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return bool
   *   True if the form was generated.
   */
  private function buildFieldWidgetForm(array &$form, FormStateInterface $form_state) {
    $field_definition = $this->getFieldDefinition();

    if (!$field_definition instanceof FieldDefinitionInterface) {
      return FALSE;
    }
    $field_storage = $field_definition->getFieldStorageDefinition();
    $this->generateFieldWidgetForm($form, $form_state, $field_definition, $field_storage);

    return TRUE;
  }

  /**
   * Generate the form formatter field formatter.
   *
   * @param array $form
   *   The source plugin settings form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage
   *   Field storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return bool
   *   False if can't generate.
   */
  private function generateFieldWidgetForm(array &$form, FormStateInterface $form_state, FieldDefinitionInterface $field_definition, FieldStorageDefinitionInterface $field_storage): bool {
    $widget_options = $this->getAvailableWidgetOptions($field_storage, $field_definition);

    if (empty($widget_options)) {
      return FALSE;
    }
    // @todo remove ui patterns formatters from the list of options ?
    // Get the formatter type from configuration.
    $widget_type = $this->getSettingsFromConfiguration(['settings', 'type']);
    $uniqueID = Html::getId(\implode('_', $this->formArrayParents ?? []) . '_field-formatter-settings-ajax');
    // Get the formatter settings from configuration.
    $form['type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Widget'),
      '#options' => $widget_options,
      '#default_value' => $widget_type,
      '#empty_option' => $this->t('- Select -'),
      // Note: We cannot use ::foo syntax, because the form is the entity form
      // display.
      '#ajax' => [
        'callback' => [__CLASS__, 'onWidgetTypeChange'],
        'wrapper' => $uniqueID,
        'method' => 'replaceWith',
      ],
    ];
    $form['settings'] = [];
    $form['third_party_settings'] = [];
    $form['settings_wrapper'] = [
      '#prefix' => '<div id="' . $uniqueID . '">',
      '#suffix' => '</div>',
    ];
    $options = [
      'field_definition' => $field_definition,
      'configuration' => $this->getFormatterConfiguration($form_state, $field_storage, $widget_options, $widget_type),
      'view_mode' => EntityDisplayBase::CUSTOM_MODE,
      'prepare' => TRUE,
    ];

    if ($widget = $this->widgetPluginManager->getInstance($options)) {
      // Settings and third_party_settings are rendered into settings_wrapper.
      // see the preRenderFormatterSettingsForm() method.
      // but they are created in the form array at the root level.
      // to ensure configuration structure does not add settings_wrapper.
      $settings_subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
      $form['settings'] = $widget->settingsForm($form, $settings_subform_state);
      $form['third_party_settings'] = $this->thirdPartySettingsForm($widget, $field_definition, $form, $form_state);
      // Should we use FormHelper::rewriteStatesSelector() ?
      // like in FieldBlock::formatterSettingsProcessCallback.
    }
    $form['#pre_render'][] = [static::class, 'preRenderFormatterSettingsForm'];

    return TRUE;
  }

  /**
   * Retrieve the settings from the configuration.
   *
   * @return array
   *   The settings.
   */
  private function getFormatterBaseSettingsFromConfiguration(): array {
    $base_container = $this->getSettingsFromConfiguration(['settings']) ?? [];

    if (isset($base_container['settings'])) {
      // Backward compatibility.
      return [
        'settings' => \is_array($base_container['settings']) ? $base_container['settings'] : [],
        'third_party_settings' => $base_container['third_party_settings'] ?? [],
      ];
    }

    return [];
  }

  /**
   * Gets the formatter configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage
   *   Field storage.
   * @param array $widget_options
   *   Array of formatters options.
   * @param string|null $widget_type
   *   The formatter name.
   *
   * @return array
   *   The formatter configuration.
   */
  private function getFormatterConfiguration(FormStateInterface $form_state, FieldStorageDefinitionInterface $field_storage, array $widget_options, ?string $widget_type = ''): array {
    $settings = [];
    $third_party_settings = [];

    if (!empty($widget_type)) {
      $widget_configuration = $this->getFormatterBaseSettingsFromConfiguration();
      $settings = $widget_configuration['settings'] ?? [];
      $third_party_settings = $widget_configuration['third_party_settings'] ?? [];
    }

    // Get default formatter type.
    if (empty($widget_type) || !isset($widget_options[$widget_type])) {
      $widget_type = $this->fieldTypePluginManager->getDefinition($field_storage->getType())['default_formatter'] ?? \key($widget_options);
      $settings = $this->widgetPluginManager->getDefaultSettings($field_storage->getType());
      $third_party_settings = [];
    }
    // Reset settings if we change the formatter.
    $triggering_element = $form_state->getTriggeringElement();

    if (!empty($triggering_element) && $triggering_element['#value'] === $widget_type) {
      $settings = $this->widgetPluginManager->getDefaultSettings($widget_type);
      $third_party_settings = [];
    }

    if (empty($settings) && !empty($widget_type)) {
      $settings = $this->widgetPluginManager->getDefaultSettings($widget_type);
      $third_party_settings = [];
    }

    return [
      'settings' => $settings,
      'third_party_settings' => $third_party_settings,
      'type' => $widget_type,
      'label' => '',
      'weight' => 0,
    ];
  }

  /**
   * Create an instance of field widget.
   *
   * @param string $widget_id
   *   The widget id.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition of field to apply widget.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @return \Drupal\Core\Field\WidgetInterface
   *   The field widget plugin.
   */
  private function createWidgetInstance(string $widget_id, FieldDefinitionInterface $field_definition) {
    $configuration = [
      'field_definition' => $field_definition,
      'settings' => [],
      'label' => '',
      'view_mode' => '',
      'third_party_settings' => [],
    ];
    /** @var \Drupal\Core\Field\WidgetInterface $instance */
    $instance = $this->widgetPluginManager->createInstance($widget_id, $configuration);

    return $instance;
  }

  /**
   * Filter the field formatter plugin given the field definition.
   *
   * @param \Drupal\Core\Field\WidgetInterface[] $widget_instances
   *   Array of field formatter plugin.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   *
   * @return array<string, WidgetInterface>
   *   Array for formatters, keyed by plugin id
   */
  private function filterWidget(array $widget_instances, FieldDefinitionInterface $field_definition): array {
    $filtered = [];

    foreach ($widget_instances as $widget) {
      if (!$widget instanceof WidgetInterface || !$widget::isApplicable($field_definition)) {
        continue;
      }
      $filtered[$widget->getPluginId()] = $widget;
    }

    return $filtered;
  }

}
