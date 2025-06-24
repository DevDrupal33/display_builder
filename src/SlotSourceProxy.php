<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;

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
   * @param array $contexts
   *   (Optional) The contexts for this builder_id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label if found, with summary if found.
   */
  public function getLabelWithSummary(array $data, array $contexts = []): string|TranslatableMarkup {
    /** @var \Drupal\ui_patterns\SourcePluginManager $sourceManager */
    $sourceManager = $this->sourceManager;
    $source = $sourceManager->getSource($data['_instance_id'] ?? '', [], $data, $contexts);

    if (!$source) {
      return '';
    }

    if ($source instanceof SourcesBundlerInterface) {
      $label = $source->getOptionLabel($data);
    }
    else {
      $label = $source->label();
    }

    $summary = $source->settingsSummary();

    if (\is_array($summary)) {
      $summary = \array_map(static fn ($v) => \trim((string) $v), $summary);

      if (!empty(\array_filter(\array_values($summary)))) {
        $label .= ': ' . \implode(', ', $summary);
      }
    }
    elseif (\is_string($summary)) {
      $label .= ': ' . \trim($summary);
    }

    return $label;
  }

}
