<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\Form\VisibilityConditionsForm;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\RenderableAltererInterface;

/**
 * Skins island plugin implementation.
 */
#[Island(
  id: 'visibility_conditions',
  label: new TranslatableMarkup('Visibility'),
  description: new TranslatableMarkup('Visibility conditions for this element.'),
  type: IslandType::Contextual,
)]
class VisibilityConditionsPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

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
    /** @var \Drupal\Core\Condition\ConditionManager $manager */
    $manager = \Drupal::service('plugin.manager.condition');

    foreach (array_keys($data) as $condition_id) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $manager->createInstance($condition_id, $data[$condition_id] ?? []);

      if (!$manager->execute($condition)) {
        return [];
      }
    }

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
    return VisibilityConditionsForm::class;
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
