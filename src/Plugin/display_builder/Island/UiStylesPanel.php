<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\Form\BlockStylesForm;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\RenderableAltererInterface;

/**
 * Styles island plugin implementation.
 */
#[Island(
  id: 'ui_styles',
  label: new TranslatableMarkup('UI Styles'),
  description: new TranslatableMarkup('Style utilities to apply to the active element.'),
  type: IslandType::Contextual,
)]
class UiStylesPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Styles';
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    if (empty($data) || !$this->isApplicable($data)) {
      return [];
    }

    $definition = $this->getPluginDefinition();

    if (!\is_array($definition)) {
      return [];
    }

    $island_id = $definition['id'] ?? '';
    $form = \Drupal::formBuilder()->getForm(static::getFormClass(), $data['_third_party_settings'][$island_id] ?? []);

    return $this->htmxEvents->onThirdPartyFormChange($form, $builder_id, $data['_instance_id'], $island_id);
  }

  /**
   * {@inheritdoc}
   */
  public function alterElement(array $element, array $data = []): array {
    // The styles key in the data array is added by BlockStylesForm.
    // Inside the styles key, there are two keys: selected and extra.
    // The structure here is the one produced by UI styles Form API element.
    $selected = $data['styles']['selected'] ?? [];
    $extra = $data['styles']['extra'] ?? '';

    return \Drupal::service('plugin.manager.ui_styles')->addClasses($element, $selected, $extra);
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
  public static function getFormClass(): string {
    return BlockStylesForm::class;
  }

  /**
   * Check if this island should be displayed.
   *
   * @param array $data
   *   The data.
   *
   * @return bool
   *   TRUE if this island should be displayed, FALSE otherwise.
   */
  private function isApplicable(array $data): bool {
    return isset($data['source_id']) && isset($data['_instance_id']);
  }

}
