<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Library island plugin implementation.
 */
#[Island(
  id: 'library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Libraries'),
  description: new TranslatableMarkup('Pick elements from libraries and drop them in the display.'),
  type: IslandType::View,
  region: 'sidebar',
)]
class LibrariesPanel extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [
      'key' => 'l',
      'help' => t('Show the libraries'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isDeferrable(): bool {
    // This panel's content is not built here: ProfileViewBuilder assembles the
    // Library islands and injects them into this pane. build() returns nothing,
    // so a deferred reload would swap the panel's content away for an empty
    // one. The panel is static anyway - it reflects profile configuration, not
    // builder state, so it never goes stale and has nothing to defer.
    //
    // Can go once the build logic moves here, @see build().
    return FALSE;
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
