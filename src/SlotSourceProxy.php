<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;

/**
 * Provide methods missing in UI Patterns.
 */
class SlotSourceProxy {

  public function __construct(
    protected PluginManagerInterface $sourceManager,
  ) {}

  /**
   * Get the label from data, with summary.
   *
   * @param array $data
   *   The data to processed.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   (Optional) The contexts, keyed by context name.
   * @param bool $label_only
   *   (Optional) Return label only and no summary. Default: false.
   *
   * @return array{label: string, summary: string}
   *   Array with keys 'label' and 'summary'.
   */
  public function getLabelWithSummary(array $data, array $contexts = [], bool $label_only = FALSE): array {
    /** @var \Drupal\ui_patterns\SourcePluginManager $sourceManager */
    $sourceManager = $this->sourceManager;
    $source = $sourceManager->getSource($data['node_id'] ?? '', [], $data, $contexts);

    if (!$source) {
      return [
        'label' => '',
        'summary' => '',
      ];
    }
    $label = (string) $source->label(TRUE);

    if ($label_only) {
      return [
        'label' => $label,
        'summary' => '',
      ];
    }

    $summary = $source->settingsSummary();
    $labelSummary = $label;

    if (\is_array($summary)) {
      $summary = \array_map(static fn ($v) => \trim((string) $v), $summary);

      if (!empty(\array_filter(\array_values($summary)))) {
        $labelSummary .= ': ' . \implode(', ', $summary);
      }
    }
    elseif (\is_string($summary)) {
      $labelSummary .= ': ' . \trim($summary);
    }

    return [
      'label' => $label,
      // Block token can contain markup.
      'summary' => Html::escape($labelSummary),
    ];
  }

}
