<?php

declare(strict_types=1);

namespace Drupal\display_builder\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The display_buildable attribute.
 *
 * @see \Drupal\display_builder\Event\PageVariantSubscriber
 *   Reads $renders_full_page to decide how a live preview is wrapped.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DisplayBuildable extends AttributeBase {

  /**
   * Constructs a new DisplayBuildable instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The human-readable name of the plugin..
   * @param string $instance_prefix
   *   Instance prefix, for consistent storage ID.
   * @param bool $renders_full_page
   *   (optional) Whether this buildable's display is a whole page by itself,
   *   header and footer included, rather than something a page wraps around.
   *   A live preview of a fragment is rendered inside whatever page wrapper the
   *   site would give it (a Page Layout, or the theme's page template), so the
   *   preview stays close to the real render; a whole page is previewed bare,
   *   since wrapping it would show the site's header and footer twice.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label,
    public readonly string $instance_prefix,
    public readonly bool $renders_full_page = FALSE,
  ) {}

}
