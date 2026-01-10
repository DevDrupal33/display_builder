<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandType;

/**
 * Builder island plugin implementation.
 *
 * Renders the builder content directly (not in an iframe) to support
 * native drag-and-drop. CSS isolation is achieved via CSS containment.
 */
#[Island(
  id: 'builder',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Builder'),
  description: new TranslatableMarkup('The Display Builder main island. Build the display with dynamic preview.'),
  type: IslandType::View,
  icon: 'tools',
)]
class BuilderPanel extends BuilderPanelBase {

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'b' => t('Show the builder'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    // Build the dropzone content.
    $dropzone = [
      '#type' => 'component',
      '#component' => 'display_builder:dropzone',
      '#props' => [
        'variant' => 'root',
      ],
      '#slots' => [
        'content' => $this->digFromSlot($builder_id, $data),
      ],
      '#attributes' => [
        // Required for JavaScript @see components/dropzone/dropzone.js.
        'data-db-id' => $builder_id,
        'data-node-title' => $this->t('Base container'),
        'data-db-root' => TRUE,
      ],
    ];

    $dropzone = $this->htmxEvents->onRootDrop($dropzone, $builder_id, $this->getPluginID());

    // Wrap in a container with CSS containment for style isolation.
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['db-build-container'],
        'data-db-build-container' => $builder_id,
        'style' => 'contain: layout style; isolation: isolate;',
      ],
      'content' => $dropzone,
    ];
  }

}
