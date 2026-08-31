<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\Island\RealRenderTrait;
use Drupal\display_builder\SourceWithSlotsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builder island plugin implementation.
 */
#[Island(
  id: 'builder',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Canvas'),
  description: new TranslatableMarkup('The Display Builder main island. Build the display with dynamic preview.'),
  type: IslandType::View,
  region: 'main',
)]
class BuilderPanel extends ViewPanelBase {

  use RealRenderTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->initRealRender($container);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'c',
      'help' => t('Show the canvas'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    return $this->buildRootDropzone($builder, $data);
  }

  /**
   * {@inheritdoc}
   */
  protected function renderComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, string $component_id, string $label, int $index): ?array {
    return $this->buildComponentRealRender($instance, $node_id, $source, $data, $component_id, $label, $index);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleBlock(InstanceInterface $instance, string $node_id, array $data, int $index = 0): ?array {
    $node_id = $node_id ?: $data['node_id'] ?? NULL;

    if (!$node_id) {
      return NULL;
    }

    $classes = ['db-block'];

    if (isset($data['source']['plugin_id'])) {
      $classes[] = 'db-block-' . \strtolower(Html::cleanCssIdentifier($data['source']['plugin_id']));
    }
    else {
      $classes[] = 'db-block-' . \strtolower(Html::cleanCssIdentifier($data['source_id']));
    }
    $build = $this->renderSource($data, $classes);
    $label_info = $this->slotSourceProxy->getLabelWithSummary($data, $this->configuration['contexts'] ?? []);

    if (isset($data['source_id'])) {
      switch ($data['source_id']) {
        case 'entity_field':
          $label_info['summary'] = (string) $this->t('Field: @label', ['@label' => $label_info['label']]);

          break;

        case 'block':
          $label_info['summary'] = (string) $this->t('Block: @label', ['@label' => $label_info['summary']]);

          break;
      }
    }

    if ($this->needsPlaceholder($this->renderer, $data, $build)) {
      $build = $this->buildEmptyPlaceholder($label_info['summary']);
    }
    elseif (!$this->useAttributesVariable($build) || $this->hasMultipleRoot($build)) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => $classes],
        'content' => $build,
      ];
    }

    // The title is expected to contain a human-readable label or summary
    // describing the block instance, used by the contextual menu for user
    // actions such as edit, delete.
    // @see components/contextual_menu/contextual_menu.js
    $title = $label_info['label'] ?? $data['source_id'] ?? $data['node_id'] ?? '';
    $build['#attributes'] = \array_merge($build['#attributes'] ?? [], $this->buildNodeAttributes($title, $index, $data['source_id'] ?? NULL));
    $build['#attributes']['data-testid'] = $data['source_id'] ?? $data['node_id'] ?? '_' . $index;

    $build = $this->htmxEvents->onInstanceClick($build, (string) $instance->id(), $node_id, $label_info['summary'] ?? $label_info['label'] ?? '', $index);

    return $build;
  }

}
