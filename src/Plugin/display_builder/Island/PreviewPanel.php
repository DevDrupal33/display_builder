<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview island plugin implementation.
 */
#[Island(
  id: 'preview',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Preview'),
  description: new TranslatableMarkup('Show a real time preview of the display.'),
  type: IslandType::View,
  icon: 'binoculars',
)]
class PreviewPanel extends IslandPluginBase {

  /**
   * The component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->componentElementBuilder = $container->get('ui_patterns.component_element_builder');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'p' => t('Show the preview'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    if (empty($data)) {
      return [];
    }

    // Page layout display need preview. We don't have source for title and
    // content, so let replace it on the fly for preview.
    if (\class_exists('Drupal\display_builder_page_layout\Entity\PageLayout') && \str_starts_with($builder_id, PageLayout::getPrefix())) {
      $content_placeholder = '<div class="db-background db-preview-placeholder"><h2>[Page] Content placeholder</h2></div>';
      $title_placeholder = '<div class="db-background db-preview-placeholder"><h1 class="title">[Page] Title placeholder</h1></div>';

      // @todo one pass and placeholder style?
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => 'page_title'], ['#markup' => $title_placeholder]);
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => 'main_page_content'], ['#markup' => $content_placeholder]);
    }

    $returned = [];

    foreach ($data as $slot) {
      $build = $this->componentElementBuilder->buildSource([], 'content', [], $slot, $this->configuration['contexts'] ?? []);
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
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

}
