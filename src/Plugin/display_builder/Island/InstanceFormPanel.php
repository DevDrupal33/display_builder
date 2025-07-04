<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\Form\SlotSourceForm;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;

/**
 * Instance form island plugin implementation.
 */
#[Island(
  id: 'instance_form',
  label: new TranslatableMarkup('Instance form'),
  description: new TranslatableMarkup('Configuration of the active element.'),
  type: IslandType::Contextual,
)]
class InstanceFormPanel extends IslandPluginBase implements IslandWithFormInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Config';
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    if (empty($data) || !$this->isApplicable($data)) {
      return [];
    }

    try {
      $contexts = $this->configuration['contexts'] ?? [];
      $form = \Drupal::formBuilder()->getForm(static::getFormClass(), $data, $contexts);

      $build = [
        'source' => $form,
      ];

      $build['update'] = [
        '#type' => 'button',
        '#value' => 'Update',
        '#submit_button' => FALSE,
        '#attributes' => [
          'type' => 'button',
          'data-wysiwyg-fix' => TRUE,
        ],
      ];

      $build = $this->htmxEvents->onInstanceFormChange($build, $builder_id, $data['_instance_id']);
      $build = $this->htmxEvents->onInstanceUpdateButtonClick($build, $builder_id, $data['_instance_id']);

      return $build;
    }
    catch (\Exception) {
      return [];
    }
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
  public function onUpdate(string $builder_id, ?string $instance_id): array {
    // Reload the form itself on update.
    $data = $this->stateManager->get($builder_id, $instance_id);
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
    return SlotSourceForm::class;
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
