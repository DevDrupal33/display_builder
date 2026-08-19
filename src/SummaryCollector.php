<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\ui_patterns\SourceInterface;

/**
 * Collects what each contextual panel has to say about a node.
 *
 * A node's settings are stored in two unrelated places: the source plugin's own
 * settings (the Config panel), and one entry per island under
 * 'third_party_settings' (Styles and friends). Both answer with a flat list of
 * items, and this is where the two become one structure, grouped by the panel
 * they belong to, so a caller can label and icon each group without knowing
 * where its items came from.
 *
 * No markup: how a group is drawn is the caller's business.
 */
class SummaryCollector {

  /**
   * The island plugin ID owning a source plugin's own settings.
   */
  private const CONFIG_ISLAND = 'contextual_form';

  public function __construct(
    protected IslandPluginManagerInterface $islandManager,
  ) {}

  /**
   * Collects the summary groups of a node.
   *
   * @param array $data
   *   The node data.
   * @param \Drupal\ui_patterns\SourceInterface|null $source
   *   (Optional) The node's source plugin, when its own settings are worth
   *   summarizing. Blocks carry theirs in their label already.
   *
   * @return array<string, array{label: string, icon: string|null, items: array<string|\Stringable>}>
   *   Groups keyed by island plugin ID, config first then third party settings
   *   in storage order. A group with no item is not returned.
   */
  public function collect(array $data, ?SourceInterface $source = NULL): array {
    $groups = [];

    if ($source !== NULL) {
      $groups = $this->addGroup($groups, self::CONFIG_ISLAND, $source->settingsSummary());
    }

    foreach ($data['third_party_settings'] ?? [] as $provider => $settings) {
      // In Display Builder, third_party_settings providers can be:
      // - an island plugin ID (our 'normal' way)
      // - a Drupal module name (the Drupal way, found in displays imported and
      // converted, not leveraged by us for now but we may do it later).
      // So, let's check the plugin ID exists before running logic.
      if (!$this->islandManager->hasDefinition($provider)) {
        continue;
      }
      $island = $this->islandManager->createInstance($provider, $settings);

      if ($island instanceof ThirdPartySettingsInterface) {
        $groups = $this->addGroup($groups, $provider, $island->getSummary());
      }
    }

    return $groups;
  }

  /**
   * Adds a group, named after its island, unless it has nothing to say.
   *
   * @param array $groups
   *   The groups collected so far.
   * @param string $island_id
   *   The island plugin ID the items belong to.
   * @param array $items
   *   The summary items.
   *
   * @return array
   *   The groups.
   */
  private function addGroup(array $groups, string $island_id, array $items): array {
    $items = \array_filter($items, static fn ($item): bool => (string) $item !== '');

    if (empty($items)) {
      return $groups;
    }
    $definition = $this->islandManager->getDefinition($island_id, FALSE) ?? [];

    $groups[$island_id] = [
      'label' => (string) ($definition['label'] ?? $island_id),
      'icon' => $definition['icon'] ?? NULL,
      'items' => \array_values($items),
    ];

    return $groups;
  }

}
