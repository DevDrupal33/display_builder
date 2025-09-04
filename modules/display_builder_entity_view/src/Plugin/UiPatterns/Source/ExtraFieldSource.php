<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'extra_field',
  label: new TranslatableMarkup('Extra field'),
  description: new TranslatableMarkup('❌ Used only for imports from Manage Display and Layout Builder. Hidden from Library.'),
  prop_types: ['slot'],
  context_definitions: [
    'entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Entity'), required: TRUE),
  ],
)]
class ExtraFieldSource extends SourcePluginBase {

  use RenderableBuilderTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'field' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->getDefinitions()[$this->getSetting('field')]['label'] ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $field = $this->getSetting('field');

    if (!$field) {
      return [];
    }
    $definition = $this->getDefinitions()[$field];

    if (!$definition) {
      return [];
    }

    $label = $definition['label'] ?? '';
    $build = $this->buildPlaceholderButton($this->t('Extra field: @field', ['@field' => $label]));
    $build['#attributes']['class'][] = 'db-background';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $options = [];

    foreach ($this->getDefinitions() as $field_name => $definition) {
      $options[$field_name] = $definition['label'];
    }
    $form['field'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->getSetting('field'),
      '#description' => $this->t('⚠️ Imported but not supported'),
    ];

    return $form;
  }

  /**
   * Get extra field definitions.
   *
   * Extra fields are not plugins but old-fashioned hooks.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *
   * @return array
   *   A list of extra field definition.
   */
  protected function getDefinitions(): array {
    $extra_fields = $this->moduleHandler->invokeAll('entity_extra_field_info');

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->getContextValue('entity');
    $entity_type = $entity->getEntityTypeId();
    $bundle_id = $entity->bundle();

    return $extra_fields[$entity_type][$bundle_id]['display'] ?? [];
  }

}
