<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandReloadEventsTrait;
use Drupal\display_builder\Island\IslandType;
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
  default_region: 'main',
  icon: 'binoculars',
)]
class PreviewPanel extends IslandPluginBase {

  use IslandReloadEventsTrait;

  /**
   * The component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->componentElementBuilder = $container->get('ui_patterns.component_element_builder');
    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'p',
      'help' => t('Show the preview'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    if (empty($data)) {
      return [];
    }

    $this->alterPreviewPlaceholder($data);

    $returned = [];

    foreach ($data as $slot) {
      $build = $this->componentElementBuilder->buildSource([], 'content', [], $slot, $this->configuration['contexts'] ?? []);
      $build = $build['#slots']['content'][0] ?? [];

      // Some sources (e.g. a comment field's "Add comment" form) throw once
      // actually rendered rather than producing empty markup - @see
      // RenderableBuilderTrait::isRenderEmptyOrFailing(). Unlike
      // BuilderPanel, which already guards every source it builds the same
      // way, nothing here previously checked this, so a throwing source
      // shipped its #lazy_builder unresolved into the page, breaking Drupal
      // core's own BigPipe processing of unrelated placeholders (e.g. the
      // Navigation module's admin toolbar) later in the same request.
      if ($this->isRenderEmptyOrFailing($this->renderer, $build)) {
        $build = $this->buildPlaceholder($this->t('[Placeholder] No preview'));
      }

      $returned[] = $build;
    }

    return $returned;
  }

  /**
   * Replace placeholder for preview.
   *
   * Some block source will not be created, create a simple placeholder to have
   * a preview instead of nothing.
   *
   * @todo move as an issue to UI Patterns?
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
