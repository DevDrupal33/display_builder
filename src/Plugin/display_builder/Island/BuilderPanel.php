<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Builder island plugin implementation.
 *
 * Renders a container that loads content via AJAX into Shadow DOM.
 * This ensures proper theme isolation - content is rendered with frontend
 * theme while the admin UI uses the backend theme.
 */
#[Island(
  id: 'builder',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Builder'),
  description: new TranslatableMarkup('The Display Builder main island. Build the display with dynamic preview.'),
  type: IslandType::View,
  icon: 'tools',
)]
class BuilderPanel extends IslandPluginBase {

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

    // Build the URL for loading content via AJAX.
    $build_url = Url::fromRoute('display_builder.build', [
      'display_builder_instance' => $builder_id,
    ])->toString();

    // Return a container that will load content via JavaScript.
    // The data-db-build-src triggers initial load.
    // The hx-trigger="db-reload" allows HTMX-triggered reloads.
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['db-build-container'],
        'data-db-build-container' => $builder_id,
        'data-db-build-src' => $build_url,
        // HTMX attributes for triggered reload.
        'hx-get' => $build_url,
        'hx-trigger' => 'db-reload',
        'hx-swap' => 'none',
      ],
      '#attached' => [
        'library' => ['display_builder/build_container'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadBuildContainer($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadBuildContainer($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->reloadBuildContainer($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->reloadBuildContainer($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->reloadBuildContainer($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadBuildContainer($builder_id);
  }

  /**
   * Returns markup that triggers a reload of the build container.
   *
   * The JavaScript listens for this element via MutationObserver and HTMX events.
   * Uses OOB swap to inject the trigger into the Builder island.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   A render array with a reload trigger element wrapped in OOB.
   */
  protected function reloadBuildContainer(string $builder_id): array {
    $trigger = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'data-db-reload-trigger' => $builder_id,
        'style' => 'display:none;',
      ],
    ];

    // Wrap in OOB to insert into the Builder island.
    // Using 'beforeend' to append rather than replacing content.
    return $this->addOutOfBand(
      $trigger,
      '#' . $this->getHtmlId($builder_id),
      'beforeend'
    );
  }

}
