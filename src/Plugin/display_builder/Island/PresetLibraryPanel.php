<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->presetConfigStorage = $container->get('entity_type.manager')->getStorage('pattern_preset');

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

    // Group presets by their 'group' key. If a preset has no group, it's assigned to 'Other'.

    foreach ($presets as $preset_id => $preset) {
      $group_name = $preset->getGroup();
      if (empty($group_name)) {
        $group_name = $this->t('Other');
      }
      $group_key = (string) $group_name;
      $grouped_presets[$group_key][$preset_id] = $preset;
    }

    // If there is only one group, render a flat list.
    if (count($grouped_presets) === 1) {
      $group_presets = reset($grouped_presets);
      foreach ($group_presets as $preset_id => $preset) {
        $keywords = \sprintf('%s %s', $preset->get('label'), $preset->get('description') ?? '');
        $preset_preview_url = Url::fromRoute('display_builder.api_preset_preview', ['preset_id' => $preset_id]);
        $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $preset->get('label'), ['preset_id' => $preset_id], $preset_preview_url, $keywords);
      }
    } else {
      // Sort groups alphabetically.
      ksort($grouped_presets);

      // Build the render array with groups.
      foreach ($grouped_presets as $group_name => $group_presets) {
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
          $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $preset->get('label'), ['preset_id' => $preset_id], $preset_preview_url, $keywords);
        }
      }
    }

    $build = $this->buildDraggables($builder_id, $build);
    $build['#source_contexts'] = $this->configuration['contexts'] ?? [];

    return $build;
  }

}
