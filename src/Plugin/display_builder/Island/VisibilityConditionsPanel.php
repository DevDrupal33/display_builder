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

/**
 * Skins island plugin implementation.
 */
#[Island(
  id: 'visibility_conditions',
  label: new TranslatableMarkup('Visibility'),
  description: new TranslatableMarkup('Visibility conditions for the active element.'),
  type: IslandType::Contextual,
)]
class VisibilityConditionsPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

  use IslandPluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $form['#tree'] = TRUE;
    $manager = \Drupal::service('plugin.manager.condition');
    $data = $form_state->getBuildInfo()['args'][0];
    $instance = $data['instance'] ?? [];
    $conditions = $manager->getDefinitions();
    unset($conditions['response_status']);

    foreach ($conditions as $condition_id => $definition) {
      if (\str_starts_with($condition_id, 'entity_bundle:')) {
        continue;
      }
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $manager->createInstance($condition_id, $instance[$condition_id] ?? []);
      $form_state->set(['conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = (\is_array($definition) && isset($definition['label'])) ? $definition['label'] : '';
      $form[$condition_id] = $condition_form;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
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
    /** @var \Drupal\Core\Condition\ConditionManager $manager */
    $manager = \Drupal::service('plugin.manager.condition');

    foreach (\array_keys($data) as $condition_id) {
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
    return $this->reloadWithLocalData($builder_id, $data, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithLocalData($builder_id, [], NULL);
  }

}
