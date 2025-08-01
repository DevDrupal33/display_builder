<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Layers island plugin implementation.
 */
#[Island(
  id: 'library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Libraries'),
  description: new TranslatableMarkup('List of elements from library islands to use in the display.'),
  type: IslandType::View,
  keyboard_shortcuts: [
    'l' => new TranslatableMarkup('Show libraries view'),
  ],
  icon: 'collection',
)]
class LibrariesPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    // This will be filled by DisplayBuilder::build()
    // @todo Move the logic here.
    return [];
  }

}
