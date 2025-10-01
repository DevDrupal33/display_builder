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

    if (empty($presets)) {
      $content = [
        '#type' => 'html_tag',
        '#tag' => 'em',
        '#value' => $this->t('No preset yet.'),
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

    foreach ($presets as $preset_id => $preset) {
      $keywords = \sprintf('%s %s', $preset->get('label'), $preset->get('description') ?? '');
      $preset_preview_url = Url::fromRoute('display_builder.api_preset_preview', ['preset_id' => $preset_id]);
      $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $preset->get('label'), ['preset_id' => $preset_id], $preset_preview_url, $keywords);
    }
    $build = $this->buildDraggables($builder_id, $build);
    $build['#source_contexts'] = $this->configuration['contexts'] ?? [];

    return $build;
  }

}
