<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\SourceWithSlotsInterface;

/**
 * Scaffold island plugin implementation.
 *
 * A Wireframe-style hierarchical view (schematic cards, no live preview) -
 * except for a configurable allowlist of component IDs, rendered with
 * their real output instead (@see BuilderPanel::buildComponentRealRender()),
 * so the actual grid/layout nesting is visible at a glance.
 *
 * Which components count as "layout" is theme-specific, hence a configurable
 * list rather than a hardcoded one.
 */
#[Island(
  id: 'scaffold',
  label: new TranslatableMarkup('Scaffold'),
  description: new TranslatableMarkup('Hierarchical view like the Wireframe, with configured components (e.g. grid rows) rendered with their real output.'),
  type: IslandType::View,
  default_region: 'main',
  icon: 'grid-3x3-gap',
)]
class ScaffoldPanel extends WireframePanelBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'g',
      'help' => t('Show the scaffold'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'components' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['components'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Render components'),
      '#description' => $this->t('Component IDs to render with their real output instead of a placeholder. One per line. Example: "ui_suite_bootstrap:grid_row_1". Recommended usage is to set components used as layout/grid.'),
      '#default_value' => $configuration['components'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $count = \count($this->getLayoutComponentIds());

    return [
      $this->formatPlural($count, '@count component rendered with real output', '@count components rendered with real output'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildSingleComponent(InstanceInterface $instance, string $node_id, SourceWithSlotsInterface $source, array $data, int $index = 0): ?array {
    $info = $this->resolveComponentInfo($source, $data, $node_id);

    if ($info === NULL) {
      return NULL;
    }

    ['component_id' => $component_id, 'label' => $label, 'instance_id' => $node_id] = $info;

    if (\in_array($component_id, $this->getLayoutComponentIds(), TRUE)) {
      return $this->buildComponentRealRender($instance, $node_id, $source, $data, $component_id, $label, $index);
    }

    return parent::buildSingleComponent($instance, $node_id, $source, $data, $index);
  }

  /**
   * The configured component IDs to render with their real output.
   *
   * @return string[]
   *   Component IDs, e.g. "ui_suite_bootstrap:grid_row_1".
   */
  private function getLayoutComponentIds(): array {
    $configuration = $this->getConfiguration();

    return \array_filter(\preg_split('/\r\n|\r|\n/', \trim($configuration['components'] ?? '')) ?: []);
  }

}
