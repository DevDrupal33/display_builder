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
 * Preview island plugin implementation.
 *
 * Renders an iframe that loads the Display Builder content with the
 * frontend theme for CSS isolation.
 */
#[Island(
  id: 'preview',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Preview'),
  description: new TranslatableMarkup('Show a real time preview of the display with frontend theme.'),
  type: IslandType::View,
  icon: 'binoculars',
)]
class PreviewPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'p' => t('Show the preview'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    // Build the preview URL for the iframe.
    $preview_url = Url::fromRoute('display_builder.preview', [
      'display_builder_instance' => $builder->id(),
    ])->toString();

    return [
      '#type' => 'component',
      '#component' => 'display_builder:preview_iframe',
      '#props' => [
        'src' => $preview_url,
        'instance_id' => $builder->id(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadPreviewIframe($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadPreviewIframe($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return $this->reloadPreviewIframe($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return $this->reloadPreviewIframe($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return $this->reloadPreviewIframe($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadPreviewIframe($builder_id);
  }

  /**
   * Returns a script to reload the preview iframe.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   A render array with inline JavaScript to reload the iframe.
   */
  protected function reloadPreviewIframe(string $builder_id): array {
    // Return a small script that reloads the preview iframe.
    // This is sent via SSE and executed on the client.
    return [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => \sprintf(
        'document.querySelector(\'[data-db-preview-iframe="%s"]\')?.contentWindow?.location.reload();',
        $builder_id
      ),
    ];
  }

}
