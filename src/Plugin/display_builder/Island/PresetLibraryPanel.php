<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\BlockLibrarySourceHelper;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\PatternPresetInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preset island plugin implementation.
 */
#[Island(
  id: 'preset_library',
  label: new TranslatableMarkup('Preset library'),
  description: new TranslatableMarkup('List of preset, already build group of components.'),
  type: IslandType::Library,
)]
class PresetLibraryPanel extends IslandPluginBase {

  /**
   * The Pattern preset storage.
   */
  protected EntityStorageInterface $presetConfigStorage;

  /**
   * The block manager.
   *
   * Used by PresetLibraryPanel to retrieve block definitions
   * for grouping presets.
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The UI Patterns source plugin manager.
   *
   * Used by PresetLibraryPanel to retrieve source definitions
   * for grouping presets.
   */
  protected SourcePluginManager $sourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->presetConfigStorage = $container->get('entity_type.manager')->getStorage('pattern_preset');
    $instance->blockManager = $container->get('plugin.manager.block');
    $instance->sourceManager = $container->get('plugin.manager.ui_patterns_source');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Presets';
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    $entity_ids = $this->presetConfigStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', TRUE)
      ->sort('weight', 'ASC')
      ->execute();
    /** @var \Drupal\display_builder\PatternPresetInterface[] $presets */
    $presets = $this->presetConfigStorage->loadMultiple($entity_ids);
    $contexts = $this->configuration['contexts'] ?? [];

    foreach ($presets as $preset_id => $preset) {
      if (!$preset->areContextsSatisfied($contexts)) {
        unset($presets[$preset_id]);
      }
    }

    if (empty($presets)) {
      $content = [
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Pattern presets are reusable arrangements of components and blocks.'),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Add presets from the contextual menu.'),
        ],
      ];
    }
    else {
      $content = $this->buildPresets($builder_id, $presets);
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:library_panel',
      '#slots' => [
        'content' => $content,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onPresetSave(string $builder_id): array {
    return $this->reloadWithGlobalData($builder_id);
  }

  /**
   * Build preset as placeholder.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param \Drupal\display_builder\PatternPresetInterface[] $presets
   *   The presets to build.
   *
   * @return array
   *   Array of preset plugins.
   */
  protected function buildPresets(string $builder_id, array $presets): array {
    $build = [];
    $grouped_presets = [];

    foreach ($presets as $preset_id => $preset) {
      $group_key = $this->getPresetGroup($preset);
      $grouped_presets[$group_key][$preset_id] = $preset;
    }

    // If there is only one group, render a flat list.
    if (count($grouped_presets) === 1) {
      $group_presets = reset($grouped_presets);
      foreach ($group_presets as $preset_id => $preset) {
        $keywords = \sprintf('%s %s', $preset->get('label'), $preset->get('description') ?? '');
        $placeholder_build = $this->buildPlaceholderButton($preset->get('label'), ['preset_id' => $preset_id], $keywords);
        $build[] = $placeholder_build;
      }
    }
    else {
      // Prepare groups for sorting by BlockLibrarySourceHelper.
      $formatted_groups = [];
      foreach ($grouped_presets as $group_name => $presets_in_group) {
        $formatted_groups[$group_name] = [
          'label' => $group_name,
          'choices' => $presets_in_group,
        ];
      }

      // Sort groups using the centralized logic from BlockLibrarySourceHelper
      // for consistent ordering.
      BlockLibrarySourceHelper::sortGroupedChoices($formatted_groups);

      // Build the render array with groups.
      foreach ($formatted_groups as $group_data) {
        $group_name = $group_data['label'];
        $group_presets = $group_data['choices'];

        $build[] = [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $group_name,
          '#attributes' => [
            'class' => ['db-filter-hide-on-search'],
          ],
        ];

        foreach ($group_presets as $preset_id => $preset) {
          $keywords = \sprintf('%s %s', $preset->get('label'), $preset->get('description') ?? '');
          $preset_preview_url = Url::fromRoute('display_builder.api_preset_preview', ['preset_id' => $preset_id]);
          $placeholder_build = $this->buildPlaceholderButtonWithPreview($builder_id, $preset->get('label'), ['preset_id' => $preset_id], $preset_preview_url, $keywords);
          $build[] = $placeholder_build;
        }
      }
    }

    $build = $this->buildDraggables($builder_id, $build);
    $build['#source_contexts'] = $this->configuration['contexts'] ?? [];

    return $build;
  }

  /**
   * Get the group for a preset.
   *
   * @param \Drupal\display_builder\PatternPresetInterface $preset
   *   The preset entity.
   *
   * @return string
   *   The group name.
   */
  protected function getPresetGroup(PatternPresetInterface $preset): string {
    $sources = $preset->getSources();
    $source_id = $sources['source_id'] ?? NULL;
    $group_name = $preset->getGroup();

    // If it's a block preset (source_id is 'block').
    if ($source_id === 'block') {
      $plugin_id = $sources['source']['plugin_id'] ?? '';
      // Default category to 'Others' (plural).
      $category = (string) $this->t('Others');

      if ($this->sourceManager->hasDefinition($source_id)) {
        $source_definition = $this->sourceManager->getDefinition($source_id);

        // For standard Drupal blocks, prioritize the category from
        // the Block Manager's definition if available.
        if ($plugin_id) {
          try {
            $block_definition = $this->blockManager->getDefinition($plugin_id);
            $block_manager_category = (string) ($block_definition['category'] ?? $this->t('Others'));
            if ($block_manager_category !== (string) $this->t('Others')) {
              $category = $block_manager_category;
            }
          }
          catch (\Exception $e) {
            // Block definition might be missing, fall back to other methods.
          }
        }

        // If category is still generic,
        // try BlockLibrarySourceHelper's choice-based logic.
        // (e.g., for entity_reference, entity_field sources).
        if ($category === (string) $this->t('Others')) {
          $choice = [
            'original_id' => $plugin_id,

          ];
          $choice_category = BlockLibrarySourceHelper::getChoiceGroupLabel($choice, $source_definition);
          if ($choice_category !== (string) $this->t('Others')) {
            $category = $choice_category;
          }
        }

        // If category is still generic, try
        // BlockLibrarySourceHelper's source-provider-based logic
        // (e.g., for 'ui_patterns' sources -> 'Utilities').
        if ($category === (string) $this->t('Others')) {
          $source_provider_category = BlockLibrarySourceHelper::getSourceGroupLabel($source_definition);
          if ($source_provider_category !== (string) $this->t('Others')) {
            $category = $source_provider_category;
          }
        }
      }
      return $category;
    }

    // Standardize default group name to 'Others' (plural).
    return (string) ($group_name ?: $this->t('Others'));
  }

}
