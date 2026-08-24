<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Expand button island plugin implementation.
 */
#[Island(
  id: 'expand',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Expand'),
  description: new TranslatableMarkup('Expand the builder to cover the current viewport.'),
  type: IslandType::Button,
  region: 'end',
)]
class ExpandButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $expand = $this->buildButton(
      '',
      'expand',
      'arrows-fullscreen',
      $this->t('Expand to cover the viewport. (shortcut: Shift+E)'),
      ['shift+e' => $this->t('Toggle expand')]
    );
    // Required for the library to work.
    $expand['#attributes']['data-set-expand'] = TRUE;
    $expand['#attached']['library'][] = 'display_builder/expand';

    return $expand;
  }

}
