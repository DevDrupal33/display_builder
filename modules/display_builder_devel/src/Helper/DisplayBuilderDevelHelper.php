<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Helper;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Helper class for Display builder devel.
 */
class DisplayBuilderDevelHelper {

  /**
   * Operations for a display builder instance.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $extra
   *   Some more specific links.
   *
   * @return array
   *   The operation links.
   */
  public static function getOperationLinks(string $builder_id, array $extra = []): array {
    $links = [
      'import' => [
        'title' => new TranslatableMarkup('Import'),
        'url' => Url::fromRoute('display_builder_devel.import', ['builder_id' => $builder_id]),
      ],
      'export' => [
        'title' => new TranslatableMarkup('Export'),
        'url' => Url::fromRoute('display_builder_devel.export', ['builder_id' => $builder_id]),
      ],
      'edit' => [
        'title' => new TranslatableMarkup('Edit'),
        'url' => Url::fromRoute('display_builder_devel.edit', ['builder_id' => $builder_id]),
      ],
      'delete' => [
        'title' => new TranslatableMarkup('Delete'),
        'url' => Url::fromRoute('display_builder_devel.delete', ['builder_id' => $builder_id]),
      ],
    ];

    return array_merge($links, $extra);
  }

}
