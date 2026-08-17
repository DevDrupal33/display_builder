<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Url;

/**
 * One display a user could travel to, built with Display Builder or not.
 *
 * The Instances panel navigates between the levels a page is assembled from,
 * and the chain it is chasing usually runs *through* a display nobody has
 * opened in Display Builder yet. So this is deliberately not an Instance
 * entity: an Instance only exists once a display has been built, and a panel
 * that can only show those fails at the exact moment it is needed.
 *
 * It also means listing is read-only. Collecting Instance entities used to
 * create and save the missing ones as a side effect of rendering a navigation
 * panel; nothing here loads or writes instance storage.
 *
 * @see \Drupal\display_builder\DisplayBuildableInterface::collectDisplays()
 * @see \Drupal\display_builder\Plugin\display_builder\Island\InstancesPanel
 */
final readonly class DisplayReference {

  /**
   * Constructs a display reference.
   *
   * @param string $instanceId
   *   The instance id this display would use. It may not exist in storage.
   * @param string $kind
   *   The buildable's own label: "Page layout", "Display", "Views"...
   * @param string $label
   *   The specific display name, from ::getDisplayLabel().
   * @param \Drupal\Core\Url $url
   *   Where the row goes: the builder when built, Manage display when not.
   * @param bool $built
   *   Whether this display uses Display Builder at all.
   * @param bool $empty
   *   Whether it is built but holds nothing. An empty display is a dead link
   *   that looks like a bug unless it is labeled.
   * @param bool $disabled
   *   Whether it is switched off. Only page layouts have the concept.
   * @param \Drupal\Core\Url|null $settingsUrl
   *   Where this display is configured: Manage display, the view edit form,
   *   the page layout edit form. An override is configured on the content it
   *   belongs to, so it points at that entity's edit form. NULL when there is
   *   no such page, or when this user may not open it.
   * @param string|null $detail
   *   What the label cannot say in the width it has. An override is named
   *   after its bundle and entity id, and an id identifies nothing to a human,
   *   so the entity's own label rides along: shown on hover, and searchable.
   */
  public function __construct(
    public string $instanceId,
    public string $kind,
    public string $label,
    public Url $url,
    public bool $built = TRUE,
    public bool $empty = FALSE,
    public bool $disabled = FALSE,
    public ?Url $settingsUrl = NULL,
    public ?string $detail = NULL,
  ) {}

  /**
   * The short status word for this display, if it needs one.
   *
   * Deliberately the same two words PageLayoutListBuilder prints, so a display
   * reads the same way in the panel and on the admin listing. The two are not
   * wired together: that listing builds its own strings from the entity.
   *
   * Only a built display has one. Not being built is not a state to report,
   * it is the one thing on the row a user can act on, and it is rendered as an
   * action; ::$built stays the single source of truth for it.
   *
   * @return string|null
   *   A status key, or NULL when the display is unremarkable or not built.
   */
  public function status(): ?string {
    if (!$this->built) {
      return NULL;
    }

    if ($this->disabled) {
      return 'disabled';
    }

    return $this->empty ? 'empty' : NULL;
  }

}
