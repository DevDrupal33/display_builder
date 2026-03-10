<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\BlockLibrarySourceHelper;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\PatternPresetInterface;
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
      '#cache' => [
        'tags' => $this->presetConfigStorage->getEntityType()->getListCacheTags(),
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
   * Build preset as placeholder grouped.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param \Drupal\display_builder\PatternPresetInterface[] $presets
   *   The presets to build.
   *
   * @return array
   *   Array of grouped preset plugins.
   */
  private function buildPresets(string $builder_id, array $presets): array {
    $build = [];
    $grouped_presets = [];

    foreach ($presets as $preset_id => $preset) {
      $group_key = $this->getPresetGroup($preset);
      $grouped_presets[$group_key][$preset_id] = $preset;
    }

    $formatted_groups = [];

    foreach ($grouped_presets as $group_name => $presets_in_group) {
      $formatted_groups[$group_name] = [
        'label' => $group_name,
        'choices' => $presets_in_group,
      ];
    }

    $is_single_group = \count($formatted_groups) === 1;

    if (!$is_single_group) {
      BlockLibrarySourceHelper::sortGroupedChoices($formatted_groups);
    }

    foreach ($formatted_groups as $group_data) {
      if (!$is_single_group) {
        $build[] = [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $group_data['label'],
          '#attributes' => [
            'class' => ['db-filter-hide-on-search'],
          ],
        ];
      }

      foreach ($group_data['choices'] as $preset) {
        $build[] = $this->buildPresetItem($builder_id, $preset, TRUE);
      }
    }

    $build = $this->buildDraggables($builder_id, $build);
    $build['#source_contexts'] = $this->configuration['contexts'] ?? [];

    return $build;
  }

  /**
   * Build a preset item.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param \Drupal\display_builder\PatternPresetInterface $preset
   *   The preset entity.
   * @param bool $with_preview
   *   Whether to include preview attributes.
   *
   * @return array
   *   The render array for the preset item.
   */
  private function buildPresetItem(string $builder_id, PatternPresetInterface $preset, bool $with_preview): array {
    $keywords = \sprintf('%s %s', $preset->get('label'), $preset->get('description') ?? '');
    $vals = ['preset_id' => $preset->id()];

    if ($with_preview) {
      $url = Url::fromRoute('display_builder.api_preset_preview', $vals);

      $build = $this->buildPlaceholderButtonWithPreview($builder_id, $preset->get('label'), $vals, $url, $keywords);
    }
    else {
      $build = $this->buildPlaceholderButton($preset->get('label'), $vals, $keywords);
    }

    $build['#attributes']['data-instance-id'][] = $preset->id();

    return $build;
  }

  /**
   * Gets the preset's group from the preset entity in the database.
   *
   * @param \Drupal\display_builder\PatternPresetInterface $preset
   *   The preset entity.
   *
   * @return string
   *   The group name, fallback to 'Others'.
   */
  private function getPresetGroup(PatternPresetInterface $preset): string {
    return $preset->getGroup() ? (string) $preset->getGroup() : (string) $this->t('Others');
  }

}
