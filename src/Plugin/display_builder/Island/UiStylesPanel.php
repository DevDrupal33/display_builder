<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\RenderableAltererInterface;
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
  description: new TranslatableMarkup('Style utilities to apply to the active element.'),
  type: IslandType::Contextual,
)]
class UiStylesPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

  use IslandPluginFormTrait;

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
