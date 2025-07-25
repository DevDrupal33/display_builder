<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Preview island plugin implementation.
 */
#[Island(
  id: 'preview',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Preview'),
  description: new TranslatableMarkup('Show a real time preview of the display.'),
  type: IslandType::View,
  keyboard_shortcuts: [
    'p' => new TranslatableMarkup('Show preview'),
  ],
  icon: 'binoculars',
)]
class PreviewPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    if (empty($data)) {
      return [];
    }

    $content_placeholder = '<div class="db-background db-preview-placeholder"><h2>Content placeholder</h2></div>';
    $title_placeholder = '<div class="db-background db-preview-placeholder"><h1 class="title">Title placeholder</h1></div>';

    // @todo one pass and placeholder style?
    DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => 'page_title'], ['#markup' => $title_placeholder]);
    DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => 'main_page_content'], ['#markup' => $content_placeholder]);

    /** @var \Drupal\ui_patterns\Element\ComponentElementBuilder $builder */
    $builder = \Drupal::service('ui_patterns.component_element_builder');
    $returned = [];

    foreach ($data as $slot) {
      $build = $builder->buildSource([], 'content', [], $slot, $this->configuration['contexts'] ?? []);
      $returned[] = $build['#slots']['content'][0] ?? [];
    }

    return $returned;
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id, ?string $current_island_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

}
