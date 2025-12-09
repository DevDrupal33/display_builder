<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
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
    if (empty($data)) {
      return [];
    }
    // Replace preview for empty block until #3561447.
    $this->alterPreviewPlaceholder($data);

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

  /**
   * Replace placeholder for preview.
   *
   * Some block source will not be created, create a simple placeholder to have
   * a preview instead of nothing. Until #3561447 is resoled.
   *
   * @param array $data
   *   The instance data to replace.
   */
  private function alterPreviewPlaceholder(array &$data): void {
    $replacements = [
      [
        'search' => ['plugin_id' => 'system_messages_block'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Block messages'),
      ],
      [
        'search' => ['source_id' => 'page_title'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Page title'),
      ],
      [
        'search' => ['source_id' => 'main_page_content'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] Page content'),
        'new_value_class' => 'db-preview-placeholder-lg',
      ],
      [
        'search' => ['source_id' => 'view_attachment_before'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View attachment before'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_exposed'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View exposed form'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_header'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View header'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_rows'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View rows'),
        'new_value_class' => 'db-preview-placeholder-lg',
      ],
      [
        'search' => ['source_id' => 'view_attachment_after'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View attachment after'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_pager'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View pager'),
      ],
      [
        'search' => ['source_id' => 'view_more'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View more'),
      ],
      [
        'search' => ['source_id' => 'view_footer'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View footer'),
        'new_value_class' => 'db-preview-placeholder-md',
      ],
      [
        'search' => ['source_id' => 'view_feed_icons'],
        'new_value_title' => new TranslatableMarkup('[Placeholder] View feed icons'),
      ],
    ];

    DisplayBuilderHelpers::findAndReplaceInArray($data, $replacements);
  }

}
