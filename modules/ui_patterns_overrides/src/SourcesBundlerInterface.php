<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_overrides;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for sources that can bundle items into groups.
 */
interface SourcesBundlerInterface {

  /**
   * Gets the data skeleton for a source item.
   *
   * @param string $item_id
   *   The item ID.
   *
   * @return array
   *   The data skeleton array.
   */
  public function getDataSkeleton(string $item_id): array;

  /**
   * Gets source items grouped by category.
   *
   * @return array
   *   Array of source items grouped by category.
   */
  public function getGroupedOptions(): array;

  /**
   * Gets a single source item by ID.
   *
   * @param string $item_id
   *   The item ID.
   *
   * @return array
   *   The source item.
   */
  public function getOption(string $item_id): array;

  /**
   * Gets a human readable label for a source item.
   *
   * @param array $data
   *   The source item data.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The item label.
   */
  public function getOptionLabel(array $data): string|TranslatableMarkup;

  /**
   * Gets all available source items.
   *
   * @return array
   *   Array of source items.
   */
  public function getOptions(): array;

}
