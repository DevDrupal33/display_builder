<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\UiPatterns\Source;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'extra_field',
  label: new TranslatableMarkup('Extra field'),
  description: new TranslatableMarkup('An entity extra field.'),
  prop_types: ['slot'],
  context_definitions: [
    'entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Entity'), required: TRUE),
    'view_mode' => new ContextDefinition('string', label: new TranslatableMarkup('View mode'), required: FALSE),
  ]
)]
class ExtraFieldSource extends SourcePluginBase {

  use RenderableBuilderTrait;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

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
    $field = $this->getSetting('field');

    if (!$field) {
      return [$this->t('No field selected')];
    }

    $definitions = $this->getDefinitions();

    if (!$definitions || !isset($definitions[$field])) {
      return [];
    }

    if (empty($definitions[$field]['label'])) {
      return [$this->t('No field selected')];
    }

    return [\strip_tags((string) $definitions[$field]['label'])];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $field = $this->getSetting('field');

    if (!$field) {
      return [];
    }

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->getContextValue('entity');
    $view_mode = $this->getContextValue('view_mode') ?? 'default';

    if (!$entity->id()) {
      return $this->renderPlaceholder($field);
    }

    // Prevent infinite recursion: buildComponents() calls
    // display->buildMultiple() which triggers
    // EntityViewDisplayTrait::buildMultiple() → buildSources() →
    // ExtraFieldSource::getPropValue() when Display builder is enabled.
    static $rendering = [];
    $render_key = $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $field . ':' . $view_mode;

    if (isset($rendering[$render_key])) {
      return [];
    }
    $rendering[$render_key] = TRUE;

    try {
      return $this->buildExtraField($entity, $field, $view_mode);
    }
    finally {
      unset($rendering[$render_key]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $options = ['' => $this->t('- Select -')];

    foreach ($this->getDefinitions() as $field_name => $definition) {
      $options[$field_name] = $definition['label'];
    }
    $form['field'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->getSetting('field'),
    ];

    return $form;
  }

  /**
   * Render placeholder when a proper entity is not loaded.
   *
   * @param string $field
   *   Extra field ID.
   *
   * @return array
   *   A renderable array.
   */
  protected function renderPlaceholder(string $field): array {
    $definition = $this->getDefinitions()[$field] ?? NULL;

    if (!$definition) {
      return [];
    }

    $label = $definition['label'] ?? $this->t('No field selected');
    $build = $this->buildPlaceholderButton($this->t('Extra field: @field', ['@field' => \strip_tags((string) $label)]));

    return $build;
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
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->getContextValue('entity');
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $extra_fields = $this->entityFieldManager->getExtraFields($entity_type_id, $bundle);

    return $extra_fields['display'] ?? [];
  }

  /**
   * Builds the extra field render array.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $field
   *   The extra field ID.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array
   *   The render array.
   */
  private function buildExtraField(FieldableEntityInterface $entity, string $field, string $view_mode): array {
    $entity_type_id = $entity->getEntityTypeId();
    $display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);

    // Register the extra field as a component on the display. Implementations
    // of hook_entity_view() and hook_ENTITY_TYPE_view() often guard rendering
    // with $display->getComponent($field_name), which would silently skip
    // the field if it is not enabled in the underlying entity view display.
    if (!$display->getComponent($field)) {
      $display->setComponent($field);
    }

    $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);
    $entity_key = (string) $entity->id();

    // Build component additions from the concrete view builder implementation.
    // For nodes this includes NodeViewBuilder::buildComponents(), which adds
    // extras like 'links' and 'langcode' before view hooks run.
    // Populate protected build defaults (e.g. CommentViewBuilder sets
    // #comment_threaded) so buildComponents() receives a complete build array.
    $get_defaults = \Closure::bind(
      function ($e, $vm) {
        // @phpstan-ignore-next-line
        return $this->getBuildDefaults($e, $vm);
      },
      $view_builder,
      \get_class($view_builder)
    );
    $defaults = $get_defaults($entity, $view_mode);
    // Strip cache metadata and the entity key — handled separately below.
    unset($defaults['#cache'], $defaults['#' . $entity_type_id]);
    $build_list = [
      $entity_key => $defaults + [
        '#' . $entity_type_id => $entity,
        '#view_mode' => $view_mode,
      ],
    ];
    $view_builder->buildComponents($build_list, [$entity_key => $entity], [$entity->bundle() => $display], $view_mode);

    /** @var array<string, mixed> $build */
    $build = [];

    if (isset($build_list[$entity_key])) {
      $build += $build_list[$entity_key];
    }

    // Invoke both the entity-type-specific and the generic hook so all
    // extra-field implementations are reached regardless of which hook they
    // use. Core EntityViewBuilder does the same.
    // @see \Drupal\Core\Entity\EntityViewBuilder::view()
    $this->moduleHandler->invokeAll($entity_type_id . '_view', [&$build, $entity, $display, $view_mode]);
    $this->moduleHandler->invokeAll('entity_view', [&$build, $entity, $display, $view_mode]);

    $field_build = $build[$field] ?? [];

    // Twig filters like |add_class and |set_attribute unconditionally add
    // #attributes to whatever render array they receive.
    // A #lazy_builder element cannot have sibling properties — the renderer
    // asserts this at Renderer.php:396.
    // Wrapping in a span means the filter targets the span element instead,
    // so the class is rendered in the DOM and the #lazy_builder stays clean.
    if (isset($field_build['#lazy_builder'])) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        'lazy' => $field_build,
      ];
    }

    return $field_build;
  }

}
