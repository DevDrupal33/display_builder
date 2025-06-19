<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;

/**
 * Block library island plugin implementation.
 */
#[Island(
  id: 'block_library',
  label: new TranslatableMarkup('Blocks library'),
  description: new TranslatableMarkup('List of available Drupal blocks to use.'),
  type: IslandType::Library,
)]
class BlockLibraryPanel extends IslandPluginBase {

  private const HIDE_BLOCK = [
    'help_block',
    'system_messages_block',
    'htmx_loader',
    'broken',
    'system_main_block',
    'page_title_block',
  ];

  private const HIDE_SOURCE = [
    'block',
    'component',
  ];

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Blocks';
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $source_contexts = $this->configuration['contexts'] ?? [];

    $sources = $this->sourceManager->getDefinitionsForPropType('slot', $source_contexts);
    /** @var \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source */
    $block_source = $this->sourceManager->createInstance('block', $this->configuration);

    return [
      $this->buildOtherSources($builder_id, $sources),
      $this->buildDrupalBlocks($builder_id, $block_source),
    ];
  }

  /**
   * Build other sources.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param array $sources
   *   Array of source definitions.
   *
   * @return array
   *   Render array for the other sources section.
   */
  protected function buildOtherSources(string $builder_id, array $sources): array {
    $placeholders = [];

    foreach ($sources as $source_id => $definition) {
      if (\in_array($source_id, self::HIDE_SOURCE, TRUE)) {
        continue;
      }
      $data = [
        'source_id' => $source_id,
      ];
      $keywords = \sprintf('%s %s %s', $definition['id'], $definition['label'] ?? '', $definition['description'] ?? '');
      $build = $this->buildPlaceholderButton($definition['label'], $data, $keywords);
      $placeholders[] = $build;
    }

    return $this->buildDraggables($builder_id, $placeholders);
  }

  /**
   * Get Drupal block plugins.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source
   *   The block source to build.
   *
   * @return array
   *   Array of block plugins, keyed by block ID with admin label as value.
   */
  protected function buildDrupalBlocks(string $builder_id, SourcesBundlerInterface $block_source): array {
    $definitions = $block_source->getOptions();
    $names = array_column($definitions, 'admin_label');
    array_multisort($names, \SORT_ASC, $definitions);
    $views_blocks = [];
    $menu_blocks = [];
    $other_blocks = [];

    foreach ($definitions as $block_id => $definition) {
      if (str_starts_with($block_id, 'views_block:')) {
        $views_blocks[$block_id] = $definition;
      }
      elseif (str_starts_with($block_id, 'system_menu_block:')) {
        $menu_blocks[$block_id] = $definition;
      }
      else {
        $other_blocks[$block_id] = $definition;
      }
    }
    $build = [
      $views_blocks ? $this->buildDrupalBlocksGroup($builder_id, $this->t('List (Views)'), $views_blocks, $block_source) : [],
      $menu_blocks ? $this->buildDrupalBlocksGroup($builder_id, $this->t('Menus'), $menu_blocks, $block_source) : [],
      $other_blocks ? $this->buildDrupalBlocksGroup($builder_id, $this->t('Others'), $other_blocks, $block_source) : [],
    ];
    $build = $this->buildDraggables($builder_id, $build);
    $build['#source_contexts'] = $this->configuration['contexts'] ?? [];

    return $build;
  }

  /**
   * Build a group of block placeholders.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The group title.
   * @param array $definitions
   *   Block plugin definitions.
   * @param \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source
   *   The block source to build.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildDrupalBlocksGroup(string $builder_id, string|TranslatableMarkup $title, array $definitions, SourcesBundlerInterface $block_source): array {
    $build = [
      [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        // We hide the group titles on search.
        '#attributes' => ['class' => 'db-filter-hide-on-search'],
        '#value' => $title,
      ],
    ];

    foreach ($definitions as $block_id => $definition) {
      if ($definition['provider'] === 'ui_patterns_blocks') {
        continue;
      }

      if (\in_array($block_id, self::HIDE_BLOCK, TRUE)) {
        continue;
      }
      $data = $block_source->getDataSkeleton($block_id);
      $keywords = \sprintf('%s %s %s', $definition['id'], $definition['admin_label'] ?? '', $definition['category'] ?? '');
      $block_preview_url = Url::fromRoute('display_builder.api_block_preview', ['block_id' => $block_id]);
      $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $definition['admin_label'], $data, $block_preview_url, $keywords);
    }

    return $build;
  }

}
