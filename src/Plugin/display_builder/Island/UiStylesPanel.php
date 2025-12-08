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
use Drupal\display_builder\ThirdPartySettingsInterface;
use Drupal\ui_styles\StylePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Styles island plugin implementation.
 *
 * @todo must move to UI Styles module.
 */
#[Island(
  id: 'ui_styles',
  label: new TranslatableMarkup('UI Styles'),
  description: new TranslatableMarkup('Apply style utilities to the active component or block'),
  type: IslandType::Contextual,
  theme: 'admin',
)]
class UiStylesPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface, ThirdPartySettingsInterface {

  use IslandWithFormTrait;

  /**
   * The UI Styles styles manager.
   */
  protected StylePluginManagerInterface $stylesManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stylesManager = $container->get('plugin.manager.ui_styles');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Styles';
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
  public function getSummary(): ?array {
    if (empty($this->data['selected'])) {
      // We do not cover 'extra'.
      return NULL;
    }

    $items = [];

    foreach ($this->data['selected'] ?? [] as $style_id => $option_key) {
      $style = $this->stylesManager->getDefinition($style_id);
      $options = $style->getOptionsAsOptions();
      $option = $options[$option_key] ?? $option_key;
      // Translate each summary item and cast to string for safe concatenation.
      $item = (string) new TranslatableMarkup('@option @label', [
        '@option' => $option,
        '@label' => \strtolower((string) $style->getLabel()),
      ]);
      $items[] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        '#value' => $item,
      ];
    }

    if (empty($items)) {
      return NULL;
    }

    $summary = [
      [
        '#type' => 'html_tag',
        '#tag' => 'em',
        '#value' => new TranslatableMarkup('Styles'),
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'ul',
        '#attributes' => [
          'class' => ['summary'],
        ],
        0 => $items,
      ],
    ];

    return $summary;
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
    return parent::isApplicable() && !empty($this->data) && \Drupal::service('module_handler')
      ->moduleExists('ui_styles');
  }

}
