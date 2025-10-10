<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\IslandWithFormTrait;
use Drupal\display_builder\RenderableAltererInterface;
use Drupal\ui_skins\CssVariable\CssVariablePluginManagerInterface;
use Drupal\ui_skins\UiSkinsUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Skins island plugin implementation.
 *
 * @todo must move to UI Styles module.
 */
#[Island(
  id: 'ui_skins',
  label: new TranslatableMarkup('UI Skins'),
  description: new TranslatableMarkup('Override CSS variables for the active component or block.'),
  type: IslandType::Contextual,
)]
class UiSkinsPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

  use IslandWithFormTrait;

  /**
   * The UI Skins CSS variables manager.
   */
  protected CssVariablePluginManagerInterface $cssVariablePluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->cssVariablePluginManager = $container->get('plugin.manager.ui_skins.css_variable');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Tokens';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $data = $form_state->getBuildInfo()['args'][0];
    $instance = $data['instance'] ?? [];
    $grouped_plugin_definitions = $this->cssVariablePluginManager->getGroupedDefinitions();

    if (empty($grouped_plugin_definitions)) {
      return;
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
          '#default_value' => $instance[$definition_id] ?? $default[':root'] ?? '',
        ];
      }

      if (!empty($variables)) {
        $variables['#type'] = 'details';
        $variables['#title'] = $group;
        $form[$group] = $variables;
      }
    }
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
  public function alterElement(array $element, array $data = []): array {
    $inline_css = [];

    foreach ($data as $variable => $value) {
      $variable = UiSkinsUtility::getCssVariableName($variable);
      $inline_css[] = "{$variable}: {$value};";
    }
    $element['#attributes']['style'] = \implode(' ', $inline_css);

    return $element;
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
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithLocalData($builder_id, []);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    return parent::isApplicable() && \Drupal::service('module_handler')
      ->moduleExists('ui_skins');
  }

  /**
   * Extract values to save in configuration.
   *
   * @param array $variables
   *   The variables to filter.
   *
   * @return array
   *   An array of filtered variables.
   */
  protected function filterValues(array $variables): array {
    $cleaned_variables = [];

    foreach ($variables as $variable => $value) {
      /** @var \Drupal\ui_skins\Definition\CssVariableDefinition $plugin_definition */
      $plugin_definition = $this->cssVariablePluginManager->getDefinition($variable, FALSE);

      if (!$plugin_definition) {
        continue;
      }

      // Remove values that do not differ from the default values of the plugin.
      if ($plugin_definition->isDefaultScopeValue(':root', $value)) {
        continue;
      }

      $cleaned_variables[$variable] = $value;
    }

    return $cleaned_variables;
  }

}
