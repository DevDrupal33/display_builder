<?php

declare(strict_types=1);

namespace Drupal\display_builder\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Island\IslandType;

/**
 * The island attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Island extends AttributeBase {

  /**
   * Constructs a new Island instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (Optional) The human-readable name of the plugin.
   * @param bool $enabled_by_default
   *   Island is enabled by default on new configuration. Default: False.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (Optional) A brief description of the plugin.
   * @param class-string|null $deriver
   *   (Optional) The deriver class.
   * @param \Drupal\display_builder\Island\IslandType|null $type
   *   (Optional) The island type from enumeration.
   * @param string|null $default_region
   *   (Optional) The island default region, if applicable.
   * @param string|null $icon
   *   (Optional) Icon for this island.
   * @param array $modules
   *   (Optional) List of other modules required for this Island.
   * @param array $attach_to
   *   (Optional) For IslandType::Floating islands only: the plugin IDs of
   *   the View islands this floating control attaches to. It renders once
   *   per listed island, alongside that island's own content, and is only
   *   visible while that island's main tab is active. Not admin-configurable
   *   - unlike default_region, this is a fixed part of the plugin
   *   definition, since a floating control is usually meaningless outside
   *   the island(s) it was built for (e.g. CSS scoped to that island).
   * @param bool $pane_header
   *   (Optional) For IslandType::Floating islands only: render inside the
   *   attach_to pane as an in-flow header bar rather than as the default fixed
   *   overlay box. Use for a control that is really the pane's own chrome (e.g.
   *   the viewport switcher above the Preview iframe). It rides with the pane
   *   automatically, so it needs no visibility syncing.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly bool $enabled_by_default = FALSE,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
    public readonly ?IslandType $type = NULL,
    public readonly ?string $default_region = NULL,
    public readonly ?string $icon = NULL,
    public readonly array $modules = [],
    public readonly array $attach_to = [],
    public readonly bool $pane_header = FALSE,
  ) {}

}
