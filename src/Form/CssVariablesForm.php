<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_skins\CssVariable\CssVariablePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display builder form.
 */
final class CssVariablesForm extends FormBase {

  /**
   * CSS variables manager.
   *
   * @var \Drupal\ui_skins\CssVariable\CssVariablePluginManagerInterface
   */
  protected $variableManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(CssVariablePluginManagerInterface $variableManager) {
    $this->variableManager = $variableManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('plugin.manager.ui_skins.css_variable')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_css_variables';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $data = $form_state->getBuildInfo()['args'][0];
    $grouped_plugin_definitions = $this->variableManager->getGroupedDefinitions();

    if (empty($grouped_plugin_definitions)) {
      return $form;
    }

    foreach ($grouped_plugin_definitions as $group => $definitions) {
      $variables = [];

      foreach ($definitions as $definition_id => $definition) {
        $default = $definition->getDefaultValues();

        if (!isset($default[':root'])) {
          continue;
        }
        $variables[$definition_id] = [
          '#type' => $definition->getType(),
          '#title' => $definition->getLabel(),
          '#default_value' => $data[$definition_id] ?? $default[':root'] ?? '',
        ];
      }

      if (!empty($variables)) {
        $variables['#type'] = 'details';
        $variables['#title'] = $group;
        $form[$group] = $variables;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $variables = $form_state->getValues();
    $variables = $this->filterValues($variables);
    $variables = $form_state->setValues($variables);
    // Those two lines are necessary to prevent the form from being rebuilt.
    // if rebuilt, the form state values will have both the computed ones
    // and the raw ones (wrapper key and values).
    $form_state->setRebuild(FALSE);
    $form_state->setExecuted();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Extract values to save in configuration.
   *
   * @param array $variables
   *   The variables to filter.
   */
  protected function filterValues(array $variables): array {
    $cleaned_variables = [];

    foreach ($variables as $variable => $value) {
      /** @var \Drupal\ui_skins\Definition\CssVariableDefinition $plugin_definition */
      $plugin_definition = $this->variableManager->getDefinition($variable, FALSE);

      if (!$plugin_definition) {
        continue;
      }

      // Remove values that do not differ from the default values of the
      // plugin.
      if ($plugin_definition->isDefaultScopeValue(':root', $value)) {
        continue;
      }

      $cleaned_variables[$variable] = $value;
    }

    return $cleaned_variables;
  }

}
