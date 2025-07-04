<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\Form\CssVariablesForm;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\RenderableAltererInterface;
use Drupal\ui_skins\UiSkinsUtility;

/**
 * Skins island plugin implementation.
 */
#[Island(
  id: 'ui_skins',
  label: new TranslatableMarkup('UI Skins'),
  description: new TranslatableMarkup('CSS variables to override for the active element.'),
  type: IslandType::Contextual,
)]
class UiSkinsPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Tokens';
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
    $inline_css = [];

    foreach ($data as $variable => $value) {
      $variable = UiSkinsUtility::getCssVariableName($variable);
      $inline_css[] = "{$variable}: {$value};";
    }
    $element['#attributes']['style'] = implode(' ', $inline_css);

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
   * {@inheritDoc}
   */
  public static function getFormClass(): string {
    return CssVariablesForm::class;
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
