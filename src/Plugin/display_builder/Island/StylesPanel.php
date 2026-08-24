<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\Island\IslandWithFormInterface;
use Drupal\display_builder\Island\IslandWithFormTrait;
use Drupal\display_builder\Island\RenderableAltererInterface;
use Drupal\display_builder\ThirdPartySettingsInterface;
use Drupal\ui_styles\StylePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Styles island plugin implementation.
 *
 * @todo must move to UI Styles module.
 */
#[Island(
  id: 'styles',
  label: new TranslatableMarkup('Styles'),
  description: new TranslatableMarkup('Apply style utilities to the active component or block'),
  type: IslandType::Contextual,
  modules: ['ui_styles'],
)]
class StylesPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface, ThirdPartySettingsInterface {

  use IslandWithFormTrait;

  /**
   * The UI Styles styles manager.
   */
  protected StylePluginManagerInterface $stylesManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stylesManager = $container->get('plugin.manager.ui_styles');
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    // The data to be received here is in the form of:
    // ['styles' => ['selected' => [], 'extra' => '']].
    $form += [
      'styles' => [
        '#type' => 'ui_styles_styles',
        '#title' => $this->t('Styles'),
        '#wrapper_type' => 'div',
        '#default_value' => \array_merge(['selected' => [], 'extra' => ''], $this->data ?? []),
      ],
      '#tree' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // The styles key in the values is added by BlockStylesForm.
    // Inside the styles key, there are two keys: selected and extra.
    // The structure here is the one produced by UI styles Form API element.
    $values = $form_state->getValue('styles');
    $form_state->setValue('selected', $values['selected'] ?? []);
    $form_state->setValue('extra', $values['extra'] ?? '');
    $form_state->unsetValue('styles');
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
    $selected = $data['selected'] ?? [];
    $extra = $data['extra'] ?? '';

    return $this->stylesManager->addClasses($element, $selected, $extra);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    $items = [];

    // We do not cover 'extra'.
    foreach ($this->data['selected'] ?? [] as $style_id => $option_key) {
      $style = $this->stylesManager->getDefinition($style_id);
      $options = $style->getOptionsAsOptions();
      $option = $options[$option_key] ?? $option_key;
      $items[] = \sprintf('%s %s', $option, \strtolower((string) $style->getLabel()));
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   *
   * No-op: a freshly attached node always has a brand-new node_id, so its
   * own contextual panel can never already be open in the second drawer for
   * this or any other reload to target.
   */
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * @see self::onAttachToRoot()
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array {
    return [];
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
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array {
    return $this->reloadWithLocalData($instance, []);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    return parent::isApplicable() && !empty($this->data) && $this->moduleHandler->moduleExists('ui_styles');
  }

}
