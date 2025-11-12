<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Layers island plugin implementation.
 */
#[Island(
  id: 'library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Libraries'),
  description: new TranslatableMarkup('Pick elements from libraries and drop them in the display.'),
  type: IslandType::View,
  icon: 'collection',
)]
class LibrariesPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'l' => t('Show the libraries'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    // @todo Move the logic here.
    // @see https://www.drupal.org/project/display_builder/issues/3542866
    return [];
  }

}
