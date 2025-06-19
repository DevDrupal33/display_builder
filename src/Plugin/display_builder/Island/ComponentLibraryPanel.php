<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Component library island plugin implementation.
 */
#[Island(
  id: 'component_library',
  label: new TranslatableMarkup('Components library'),
  description: new TranslatableMarkup('List of available Components to use.'),
  type: IslandType::Library,
)]
class ComponentLibraryPanel extends IslandPluginBase {

  /**
   * Component provider to exclude.
   *
   * Include known UI Suite modules.
   *
   * @todo have this as a configuration in the island.
   *
   * @var array
   *   The providers to exclude.
   */
  private const PROVIDER_EXCLUDE = ['display_builder', 'sdc_devel'];

  /**
   * The definitions filtered for current theme.
   *
   * @var array
   *   The definitions filtered.
   */
  private array $definitionsFiltered = [];

  /**
   * The definitions filtered and grouped for current theme.
   *
   * @var array
   *   The definitions filtered and grouped.
   */
  private array $definitionsGrouped = [];

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Components';
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    // Try to call only once for each sub islands.
    $definitions = $this->getDefinitionsForProvider();

    $this->definitionsFiltered = $definitions['filtered'] ?? [];
    $this->definitionsGrouped = $definitions['grouped'] ?? [];

    $panes = [
      'grouped' => [
        'title' => $this->t('Grouped'),
        'content' => $this->getComponentsGrouped($builder_id),
      ],
      'variants' => [
        'title' => $this->t('Variants'),
        'content' => $this->getComponentsVariants($builder_id),
      ],
      'mosaic' => [
        'title' => $this->t('Mosaic'),
        'content' => $this->getComponentsMosaic($builder_id),
      ],
    ];
    $tabs = [];
    $content = [];

    foreach ($panes as $pane_id => $pane) {
      $id = 'db-' . $builder_id . '-components-tab---' . $pane_id;
      $tabs[] = [
        'title' => $pane['title'],
        'url' => '#' . $id,
      ];
      $content[] = $this->wrapContent($pane['content'], $id);
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:library_panel',
      '#slots' => [
        'tabs' => $this->buildTabs('db-' . $builder_id . '-components-tabs', $tabs),
        'content' => $content,
      ],
    ];
  }

  /**
   * Gets the grouped components view.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return array
   *   A renderable array containing the grouped components.
   */
  private function getComponentsGrouped(string $builder_id): array {
    $build = [];

    /** @var \Drupal\ui_patterns_overrides\SourcesBundlerInterface $source */
    $source = $this->sourceManager->createInstance('component');

    foreach ($this->definitionsGrouped as $group_name => $group) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $group_name,
        '#attributes' => [
          'class' => ['db-filter-hide-on-search'],
        ],
      ];

      foreach ($group as $component_id => $definition) {
        $component_id = (string) $component_id;
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);

        $data = $source->getDataSkeleton($component_id);
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);
        $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $definition['annotated_name'], $data, $component_preview_url, $keywords);
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Gets the components variants view.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return array
   *   A renderable array containing the variants placeholders.
   */
  private function getComponentsVariants(string $builder_id): array {
    $build = [];

    /** @var \Drupal\ui_patterns_overrides\SourcesBundlerInterface $source */
    $source = $this->sourceManager->createInstance('component');

    foreach ($this->definitionsFiltered as $component_id => $definition) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $definition['annotated_name'],
        '#attributes' => [
          'data-filter-parent' => $definition['machineName'],
        ],
      ];
      $data = $source->getDataSkeleton($component_id);

      if (!isset($definition['variants'])) {
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);
        $build_variant = $this->buildPlaceholderButtonWithPreview($builder_id, $this->t('Default'), $data, $component_preview_url, $keywords);
        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
        $build[] = $build_variant;
        continue;
      }

      foreach ($definition['variants'] ?? [] as $variant_id => $variant) {
        $params = ['component_id' => $component_id, 'variant_id' => $variant_id];
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', $params);
        $data['source']['component']['variant_id'] = [
          'source_id' => 'select',
          'source' => [
            'value' => $variant_id,
          ],
        ];
        // Used for search filter.
        $keywords = \sprintf('%s %s %s', $definition['label'], $variant['title'], $definition['provider']);
        $build_variant = $this->buildPlaceholderButtonWithPreview($builder_id, $variant['title'], $data, $component_preview_url, $keywords);
        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
        $build[] = $build_variant;
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Gets the mosaic view of components.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return array
   *   A renderable array containing the mosaic view of components.
   */
  private function getComponentsMosaic(string $builder_id): array {
    $components = [];

    $source = $this->sourceManager->createInstance('component');

    foreach (array_keys($this->definitionsFiltered) as $component_id) {
      $component_id = (string) $component_id;
      $component = $this->sdcManager->find($component_id);
      $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);

      /** @var \Drupal\ui_patterns_overrides\SourcesBundlerInterface $source */
      $vals = $source->getDataSkeleton($component_id);
      $thumbnail = $component->metadata->getThumbnailPath();

      // Used for search filter.
      $keywords = \sprintf('%s %s', $component->metadata->name, str_replace(':', ' ', $component_id));
      $build = $this->buildPlaceholderCardWithPreview($component->metadata->name, $vals, $component_preview_url, $keywords, $thumbnail);
      $components[] = $build;
    }

    return $this->buildDraggables($builder_id, $components, 'mosaic');
  }

  /**
   * Get definition for current provider.
   *
   * @return array
   *   The definitions filtered and grouped.
   */
  private function getDefinitionsForProvider(): array {
    // @phpstan-ignore-next-line
    $definitions = $this->sdcManager->getSortedDefinitions($this->sdcManager->getDefinitions());
    $provider = \Drupal::configFactory()->get('system.theme')->get('default');

    $filtered_definitions = $grouped_definitions = [];
    foreach ($definitions as $id => $definition) {
      if (isset($definition['status']) && $definition['status'] === 'obsolete') {
        continue;
      }
      if ($definition['extension_type']->value === 'theme' && $definition['provider'] !== $provider) {
        continue;
      }
      if (in_array($definition['provider'], self::PROVIDER_EXCLUDE)) {
        continue;
      }
      // Because of multiple themes, annotated name contains the theme name.
      // We do not need it.
      $definition['annotated_name'] = $definition['name'] ?? 'n/a';
      $filtered_definitions[$id] = $definition;
      // @phpstan-ignore-next-line
      $grouped_definitions[(string) $definition['category']][$id] = $definition;
    }

    // Order list ignoring starting '(' that is used for components names that
    // are sub components.
    uasort($filtered_definitions, function ($a, $b) {
      // @phpstan-ignore-next-line
      $nameA = ltrim($a['name'] ?? $a['label'], '(');
      // @phpstan-ignore-next-line
      return strnatcasecmp($nameA, ltrim($b['name'] ?? $b['label'], '('));
    });

    return [
      'grouped' => $grouped_definitions,
      'filtered' => $filtered_definitions,
    ];
  }

}
