/**
 * @param Drupal
 * @param once
 * @param htmx
 * @file
 * Defers rebuilding island panels the user cannot currently see.
 *
 * Every state-mutating action fans out to all enabled islands, each returning
 * an out-of-band swap. Most View panels are off screen at that moment - the
 * main area shows one tab at a time, the start drawer one pane at a time - so
 * that work and markup is thrown away.
 *
 * This tells the server which panels are actually on screen, and rebuilds the
 * others only when they come back into view:
 *
 *   1. On every builder request, send the visible island IDs in a header and
 *      flag every other deferrable pane as stale.
 *   2. When a stale pane becomes visible, fetch just that island.
 *
 * The server needs no way to report back what it skipped: the client sent the
 * visible list, so it already knows the complement went stale. An absent
 * header means "render everything", so anything that does not run this file -
 * server-sent events, functional tests - keeps the pre-deferral behavior.
 *
 * @see \Drupal\display_builder\Controller\ApiControllerBase::VISIBLE_ISLANDS_HEADER
 * @see \Drupal\display_builder\Event\DisplayBuilderEventsSubscriber::shouldDefer()
 * @see \Drupal\display_builder\Controller\ApiController::reloadIsland()
 */

((Drupal, once, htmx) => {
  const HEADER = 'X-DB-Visible-Islands';
  const STALE_ATTR = 'data-db-stale';

  /**
   * Marks a panel whose refresh request is in flight.
   *
   * Only blocks interaction. The dimming is driven by STALE_ATTR instead, so it
   * is already in place before the panel is ever shown.
   *
   * @see components/display_builder/css/display_builder.css
   *
   * @type {string}
   */
  const LOADING_CLASS = 'db-island--loading';

  /**
   * Panes whose visibility is worth reporting to the server.
   *
   * Broader than DEFERRABLE on purpose. What the user can see is a fact the
   * server may use for more than one decision - a Floating island is deferred
   * based on whether the panel it attaches to is visible, and that panel might
   * not itself be deferrable - so the report stays complete even where nothing
   * is deferred.
   *
   * @type {string}
   */
  const REPORTABLE = '.db-island-view, .db-island-preview, .db-island-floating';

  /**
   * Panes the server has marked as safe to leave stale while off screen.
   *
   * Deliberately not re-derived from island types here: the server decides
   * (@see \Drupal\display_builder\Island\IslandInterface::isDeferrable()) and
   * stamps the attribute, so an island that cannot survive a standalone reload
   * simply never gets it. An earlier version matched on island type in this
   * file and wrongly included the Libraries panel, whose content is injected
   * by ProfileViewBuilder rather than built by the island - reloading it
   * emptied the panel.
   *
   * @type {string}
   */
  const DEFERRABLE = '[data-db-deferrable]';

  /**
   * Extracts an island's plugin ID from its pane element.
   *
   * Panes are stamped id="island-{builderId}-{islandId}" by
   * IslandPluginBase::getHtmlId(). The builder ID is stripped by length rather
   * than by splitting on '-', since both IDs may contain hyphens.
   *
   * @param {HTMLElement} pane - The island pane element
   * @param {string} builderId - The builder id
   * @return {string|null} The island plugin ID, or null if it does not parse
   */
  function islandIdOf(pane, builderId) {
    const prefix = `island-${builderId}-`;
    return pane.id.startsWith(prefix) ? pane.id.slice(prefix.length) : null;
  }

  /**
   * Reports whether an element is actually rendered on screen.
   *
   * Deliberately measured rather than inferred from class names: main tabs
   * hide with `display: none` (.shoelace-tabs__tab--hidden), drawer panes with
   * .db-sidebar__pane--hidden, and the drawer itself collapses to zero width.
   * A zero-area box covers all three, and keeps working if any of those
   * mechanisms change.
   *
   * @param {HTMLElement} element - The element to test
   * @return {boolean} True if the element occupies space
   */
  function isVisible(element) {
    const rect = element.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  }

  /**
   * Collects what to tell the server, and which panes that leaves stale.
   *
   * The two lists come from different selectors: every reportable pane's
   * visibility is worth sending, but only a deferrable one is allowed to go
   * stale.
   *
   * @param {HTMLElement} builder - The builder element
   * @return {{visible: string[], stale: HTMLElement[]}} Island IDs the user can
   *   see, and the deferrable panes the server is about to skip
   */
  function collectPaneState(builder) {
    const visible = [];
    const stale = [];

    builder.querySelectorAll(REPORTABLE).forEach((pane) => {
      const islandId = islandIdOf(pane, builder.id);
      if (!islandId) return;
      if (isVisible(pane)) {
        visible.push(islandId);
      } else if (pane.matches(DEFERRABLE)) {
        stale.push(pane);
      }
    });

    return { visible, stale };
  }

  /**
   * Rebuilds a stale pane, then clears its flag.
   *
   * The flag is cleared only once the swap has landed, so a request that fails
   * or is aborted leaves the pane stale and it is retried next time the user
   * looks at it. Requests are not de-duplicated because the flag is removed
   * before any second trigger can observe a still-stale pane in practice; a
   * duplicate would be harmless anyway, since the response is idempotent.
   *
   * @param {HTMLElement} builder - The builder element
   * @param {HTMLElement} pane - The stale pane to refresh
   */
  function refreshPane(builder, pane) {
    const islandId = islandIdOf(pane, builder.id);
    if (!islandId) return;

    const url = `${drupalSettings.path.baseUrl}api/display-builder/${builder.id}/island/${islandId}`;
    // The swap replaces the pane's inner HTML, not the pane itself, so this
    // class and the stale attribute both survive it - the pane stays dimmed
    // until the new content has actually landed.
    pane.classList.add(LOADING_CLASS);
    htmx
      .ajax('GET', url, { source: pane, swap: 'none' })
      .then(() => pane.removeAttribute(STALE_ATTR))
      .catch(() => {
        // Left stale on purpose - retried when the pane is next revealed.
      })
      .finally(() => pane.classList.remove(LOADING_CLASS));
  }

  /**
   * Wires deferral onto a builder element.
   *
   * @param {HTMLElement} builder - The builder element
   */
  function initDeferredIslands(builder) {
    builder.addEventListener('htmx:configRequest', (event) => {
      // The reload endpoint is the mechanism itself: it renders exactly one
      // island and dispatches no event, so reporting visibility for it would
      // be meaningless and re-flagging panes would undo the refresh in flight.
      if (event.detail.path.includes(`/island/`)) return;

      const { visible, stale } = collectPaneState(builder);
      event.detail.headers[HEADER] = visible.join(',');
      // Flagged now rather than on response: the server has already been told
      // to skip these, so they are stale whatever the response turns out to be.
      // Flagging early is also what dims them while they are still hidden, so
      // they are already greyed the instant the user switches to one.
      stale.forEach((pane) => pane.setAttribute(STALE_ATTR, ''));
    });

    // Tab switches and drawer toggles are plain class changes with no event to
    // listen for (@see components/shoelace/tabs/tabs.js syncPanes(), and
    // js/sidebar.js toggleFirstDrawerContent()).
    //
    // Watching those class changes directly does not work: the start drawer
    // animates its width open, so one frame after the class flips the pane
    // still measures zero and would be judged hidden - and since no further
    // mutation follows, nothing would ever re-check it and the pane would stay
    // stale for good. An IntersectionObserver reports the element once it
    // actually occupies space, whenever during the transition that happens.
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && entry.target.hasAttribute(STALE_ATTR)) {
          refreshPane(builder, entry.target);
        }
      });
    });
    builder
      .querySelectorAll(DEFERRABLE)
      .forEach((pane) => observer.observe(pane));
  }

  /**
   * Drupal behavior deferring hidden island rebuilds.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the deferred island behavior.
   */
  Drupal.behaviors.displayBuilderDeferredIslands = {
    attach(context) {
      once('dbDeferredIslands', '.display-builder', context).forEach(
        (builder) => {
          if (!builder.id) return;
          initDeferredIslands(builder);
        },
      );
    },
  };
})(Drupal, once, htmx);
