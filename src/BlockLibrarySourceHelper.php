<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Helper class for block library source handling.
 */
class BlockLibrarySourceHelper {

  private const HIDE_BLOCK = [
    'htmx_loader',
    'broken',
    'system_main_block',
    'page_title_block',
  ];

  private const HIDE_CHOICE_ID = [
    'field:user:user:pass',
  ];

  /**
   * Get the choices grouped by category.
   *
   * @param array $sources
   *   An array of all possible sources.
   * @param array $exclude_provider
   *   (Optional) An array of providers to hide.
   * @param array $exclude_by_id
   *   (Optional) An array of id to hide.
   * @param bool $preview
   *   (Default true) Whether to show preview on hover.
   *
   * @return array
   *   An array of grouped choices.
   */
  public static function getGroupedChoices(array $sources, array $exclude_provider = [], array $exclude_by_id = [], bool $preview = TRUE): array {
    $choices = self::getChoices($sources, $exclude_provider, $exclude_by_id, $preview);
    $default_category = (string) new TranslatableMarkup('Others');

    $categories = [];

    foreach ($choices as $choice) {
      $category = $choice['group'] ?? $default_category;

      if ($category instanceof MarkupInterface) {
        $category = (string) $category;
      }

      if (!isset($categories[$category])) {
        $categories[$category] = [
          'label' => $category,
          'choices' => [],
        ];
      }
      $categories[$category]['choices'][] = $choice;
    }
    self::sortGroupedChoices($categories);

    return $categories;
  }

  /**
   * Sorts the grouped choices based on arbitrary weight.
   *
   * @param array $categories
   *   The categories to sort, passed by reference.
   */
  public static function sortGroupedChoices(array &$categories): void {
    // Public for reuse by PresetLibraryPanel.
    $category_weight = [
      // Different builder contexts.
      (string) new TranslatableMarkup('Page') => 1,
      (string) new TranslatableMarkup('Views') => 1,
      (string) new TranslatableMarkup('Fields') => 1,
      (string) new TranslatableMarkup('Referenced entities') => 2,
      // Global categories.
      (string) new TranslatableMarkup('Menus') => 3,
      (string) new TranslatableMarkup('System') => 3,
      (string) new TranslatableMarkup('User') => 3,
      (string) new TranslatableMarkup('Utilities') => 3,
      (string) new TranslatableMarkup('Forms') => 4,
      (string) new TranslatableMarkup('Lists (Views)') => 5,
      (string) new TranslatableMarkup('Others') => 6,
      (string) new TranslatableMarkup('Dev tools') => 99,
    ];

    // Sort categories by predefined weight, then by natural string comparison
    // of labels.
    \uasort($categories, static function ($a, $b) use ($category_weight) {
      $weight_a = $category_weight[$a['label']] ?? 98;
      $weight_b = $category_weight[$b['label']] ?? 98;

      if ($weight_a === $weight_b) {
        return \strnatcmp($a['label'], $b['label']);
      }

      return $weight_a <=> $weight_b;
    });
  }

  /**
   * Get the choices from all sources.
   *
   * @param array $sources
   *   An array of all possible sources.
   * @param array $exclude_provider
   *   An array of providers to hide.
   * @param array $exclude_by_id
   *   (Optional) An array of id to hide.
   * @param bool $preview
   *   (Default true) Whether to show preview on hover.
   *
   * @return array
   *   An array of choices.
   */
  public static function getChoices(array $sources, array $exclude_provider, array $exclude_by_id = [], bool $preview = TRUE): array {
    $result_choices = [];

    foreach ($sources as $source_id => $source_data) {
      $definition = $source_data['definition'];
      $source = $source_data['source'];

      // If no choices, add the source as a single choice.
      if (!isset($source_data['choices'])) {
        $id = $definition['id'] ?? $source_id;

        if (\in_array($id, $exclude_by_id, TRUE)) {
          continue;
        }

        $label = $definition['label'] ?? $source_id;
        $keywords = \sprintf('%s %s %s', $definition['id'], $definition['label'] ?? $source_id, $definition['description'] ?? '');

        $result_choices[] = [
          'label' => (string) $label,
          'data' => ['source_id' => $source_id],
          'keywords' => $keywords,
          'preview' => NULL,
          'group' => self::getSourceGroupLabel($definition),
        ];

        continue;
      }

      // Mostly for block source, iterate over choices.
      $choices = $source_data['choices'];

      foreach ($choices as $choice_id => $choice) {
        if (\in_array($choice_id, $exclude_by_id, TRUE)) {
          continue;
        }

        if (\in_array($choice_id, self::HIDE_CHOICE_ID, TRUE)) {
          continue;
        }

        if (!self::isChoiceValid($choice, $definition, $exclude_provider)) {
          continue;
        }

        $label = $choice['label'] ?? $choice_id;
        $keywords = \sprintf('%s %s %s %s', $definition['id'], $label, $definition['description'] ?? '', $choice_id);

        $result_choices[] = [
          'label' => (string) $label,
          'keywords' => $keywords,
          'data' => [
            'source_id' => $source_id,
            'source' => $source->getChoiceSettings($choice_id),
          ],
          'preview' => $preview ? Url::fromRoute('display_builder.api_block_preview', ['block_id' => $choice_id]) : NULL,
          'group' => self::getChoiceGroupLabel($choice, $definition),
        ];
      }
    }

    // Sort alphabetically within groups by label.
    \usort($result_choices, static function (array $a, array $b): int {
      return \strnatcasecmp((string) ($a['label']), (string) ($b['label']));
    });

    return $result_choices;
  }

  /**
   * Validate a choice against the source definition and allowed providers.
   *
   * @param array $choice
   *   The choice to validate.
   * @param array $source_definition
   *   The source definition.
   * @param array $exclude_provider
   *   The excluded providers.
   *
   * @return bool
   *   Whether the choice is valid or not.
   */
  private static function isChoiceValid(array &$choice, array &$source_definition, array $exclude_provider = []): bool {
    $provider = $choice['provider'] ?? '';

    if ($provider) {
      if (\in_array($provider, $exclude_provider, TRUE)) {
        return FALSE;
      }
    }

    if ($source_definition['id'] === 'block') {
      $block_id = $choice['original_id'] ?? '';

      if ($block_id && \in_array($block_id, self::HIDE_BLOCK, TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get the group label for a source.
   *
   * @param array $source_definition
   *   The source definition to use for the group.
   *
   * @return string
   *   The group label for the choice.
   */
  private static function getSourceGroupLabel(array $source_definition): string {
    $group = (string) new TranslatableMarkup('Others');

    $provider = $source_definition['provider'] ?? NULL;

    if (!$provider) {
      return $group;
    }

    switch ($provider) {
      case 'display_builder_page_layout':
        $group = (string) new TranslatableMarkup('Page');

        break;

      case 'display_builder_views':
      case 'ui_patterns_views':
        $group = (string) new TranslatableMarkup('Views');

        break;

      case 'display_builder_entity_view':
        $group = (string) new TranslatableMarkup('Fields');

        break;

      case 'display_builder_dev_tools':
        $group = (string) new TranslatableMarkup('Dev tools');

        break;

      case 'display_builder':
      case 'ui_icons_patterns':
      case 'ui_patterns':
        $group = (string) new TranslatableMarkup('Utilities');

        break;

      default:
        break;
    }

    return $group;
  }

  /**
   * Get the group label for a choice.
   *
   * @param array $choice
   *   The choice to get the group for.
   * @param array $source_definition
   *   The source definition to use for the group.
   *
   * @return string
   *   The group label for the choice.
   */
  private static function getChoiceGroupLabel(array $choice, array $source_definition): string {
    $group = (string) new TranslatableMarkup('Others');
    $source_id = $source_definition['id'] ?? NULL;

    if (!$source_id) {
      return $group;
    }

    switch ($source_id) {
      case 'block':
        if (!empty($choice['group'])) {
          $group = $choice['group'];
        }

        break;

      case 'entity_reference':
        $group = (string) new TranslatableMarkup('Referenced entities');

        break;

      case 'entity_field':
        $group = (string) new TranslatableMarkup('Fields');

        break;

      default:
        break;
    }

    return $group;
  }

}
