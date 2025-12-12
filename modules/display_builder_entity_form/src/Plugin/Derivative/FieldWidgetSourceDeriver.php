<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Plugin\Derivative;

use Drupal\Component\Plugin\PluginBase;
use Drupal\ui_patterns\Plugin\Derivative\EntityFieldSourceDeriverBase;

/**
 * Provides derivable context for every field.
 */
class FieldWidgetSourceDeriver extends EntityFieldSourceDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeDefinitionsForEntityBundleField(string $entity_type_id, string $bundle, string $field_name, array $base_plugin_derivative): void {
    $id = \implode(PluginBase::DERIVATIVE_SEPARATOR, [
      $entity_type_id,
      $bundle,
      $field_name,
    ]);
    $this->derivatives[$id] = \array_merge(
      $base_plugin_derivative,
      [
        'id' => $id,
        'label' => $this->t('[Field] Widget'),
        'description' => $this->t('Output of a field widget.'),
        'metadata' => \array_merge($base_plugin_derivative['metadata'], [
          'field_widget' => TRUE,
        ]),
        'tags' => \array_merge($base_plugin_derivative['tags'], ['field_widget']),
        'prop_types' => ['slot'],
      ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeDefinitionsForEntityStorageField(string $entity_type_id, string $field_name, array $base_plugin_derivative): void {
    $id = \implode(PluginBase::DERIVATIVE_SEPARATOR, [
      $entity_type_id,
      '',
      $field_name,
    ]);
    $this->derivatives[$id] = \array_merge(
      $base_plugin_derivative,
      [
        'id' => $id,
        'label' => $this->t('[Field] Widget'),
        'description' => $this->t('Output of a field widget.'),
        'metadata' => \array_merge($base_plugin_derivative['metadata'], [
          'field_widget' => TRUE,
        ]),
        'tags' => \array_merge($base_plugin_derivative['tags'], ['field_']),
        'prop_types' => ['slot'],
      ]);
  }

}
