/**
 * Self documenting behaviors for managing the Library/Tree and Settings
 * sidebars in Display Builder.
 */
/* eslint no-use-before-define: 0 */
/* eslint no-unused-expressions: 0 */
/* eslint no-console: 0 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

const START_DEFAULT_WIDTH = 200;
const END_DEFAULT_WIDTH = 270;
const MIN_WIDTH = 150;

/**
 * Mirrors a sidebar's own current width onto a local CSS custom property on
 * :root (@see components/display_builder/css/display_builder.css,
 * css/sidebar.css) - both .display-builder__main's margin and (case 2 only,
 * an embedded builder route) .dialog-off-canvas-main-canvas's margin need
 * it, and the latter is an ancestor of .display-builder that a property set
 * only on .display-builder itself could never reach (custom properties only
 * inherit downward). Purely local cross-component layout, not a Drupal core
 * displace concern unlike --drupal-displace-offset-*, nothing outside Display
 * Builder itself needs to know a sidebar's width.
 *
 * @param {'start'|'end'} side
 *   Which sidebar.
 * @param {number} width
 *   The sidebar's current width in pixels, or 0 when collapsed.
 */
Drupal.displayBuilder.setSidebarWidth = (side, width) => {
  document.documentElement.style.setProperty(
    `--db-sidebar-${side}-width`,
    `${width}px`,
  );
};

/**
 * Handles the click event for the second sidebar (Settings) trigger.
 *
 * Allow open and close second sidebar based on a clicked element in a view
 * panel. Second click will close the sidebar. Title of the sidebar is
 * set to the element title.
 * There is a special 'close' case used by delete menu and 'dragend' for when
 * something is dragged in the builder.
 *
 * @param {Object} builder
 *   The builder.
 * @param {Object} trigger
 *   The trigger button element that was clicked.
 * @param {Object} event
 *   The event associated.
 * @param {string} type
 *   The type, can be 'click', 'close' or 'dragend'.
 * @prop {string} trigger.variant
 *   The current variant of the trigger button (e.g., 'default', 'primary').
 */
Drupal.displayBuilder.handleSecondDrawer = (builder, trigger, event, type) => {
  if (!type) return;

  const secondDrawer = builder.querySelector('#db-second-drawer');
  if (!secondDrawer) return;

  const title = secondDrawer.querySelector('.db-sidebar__title');
  const isOpen = () => !secondDrawer.classList.contains('is-collapsed');
  const open = () => {
    secondDrawer.classList.remove('is-collapsed');
    const width =
      Drupal.displayBuilder.LocalStorageManager.get('endDrawerWidth') ||
      END_DEFAULT_WIDTH;
    secondDrawer.style.width = `${width}px`;
    Drupal.displayBuilder.setSidebarWidth('end', width);
  };
  const close = () => {
    secondDrawer.classList.add('is-collapsed');
    // Remove the inline width so the CSS width:0 collapse rule can apply -
    // an inline style always outranks a stylesheet class rule.
    secondDrawer.style.removeProperty('width');
    Drupal.displayBuilder.setSidebarWidth('end', 0);
  };
  const setTitle = (value) => {
    if (title) title.textContent = value;
  };

  // Handle 'close' type. Used when contextual menu > delete is used, and by
  // initSecondDrawer()'s own collapse() (close button, Escape key).
  if (type === 'close') {
    if (isOpen()) {
      close();
      secondDrawer.removeAttribute('data-trigger-node-id');
      setTitle(Drupal.t('Settings'));
      Drupal.displayBuilder.LocalStorageManager.remove(
        'secondDrawerActiveNodeId',
        builder.id,
      );
    }
    return;
  }

  // Handle 'dragend' type. Used when a block or component is moved from the
  // library. We ignore move from inside.
  if (type === 'dragend') {
    // Only act if sidebar is open and no nodeId is present.
    if (
      isOpen() &&
      !(
        event.target.dataset?.nodeId ||
        secondDrawer.getAttribute('data-trigger-node-id') === ''
      )
    ) {
      setTitle(event.target.dataset?.nodeTitle ?? Drupal.t('Settings'));
      // We don't have a node id yet, better to remove the trigger attribute.
      secondDrawer.removeAttribute('data-trigger-node-id');
    }
    return;
  }

  /**
   * Toggle the open class on the trigger element(s).
   *
   * @param {string} triggerId
   *   The trigger node ID.
   * @param {boolean} forceRemove
   *   Whether to force remove the open class. Defaults to false.
   */
  const toggleOpenClassOnClick = (triggerId, forceRemove = false) => {
    const triggers = builder.querySelectorAll(`[data-node-id="${triggerId}"]`);
    triggers.forEach((triggerElement) => {
      if (isOpen() && !forceRemove) {
        triggerElement.classList.add('db-node-contextual-open');
      } else {
        triggerElement.classList.remove('db-node-contextual-open');
      }
    });
  };

  // Handle 'click' type. Main action, when something is clicked in the builder.
  if (type === 'click') {
    const triggerId = trigger.dataset.nodeId || '';
    const triggerNodeId = secondDrawer.dataset?.triggerNodeId;

    // First case is opening a closed sidebar.
    if (!isOpen()) {
      setTitle(trigger.dataset.nodeTitle);
      secondDrawer.setAttribute('data-trigger-node-id', triggerId);
      open();
      toggleOpenClassOnClick(triggerId);
      Drupal.displayBuilder.LocalStorageManager.set(
        'secondDrawerActiveNodeId',
        triggerId,
        builder.id,
      );
    } else if (triggerNodeId === triggerId) {
      // Second case is closing the sidebar.
      close();
      secondDrawer.removeAttribute('data-trigger-node-id');
      toggleOpenClassOnClick(triggerId);
      Drupal.displayBuilder.LocalStorageManager.remove(
        'secondDrawerActiveNodeId',
        builder.id,
      );
    } else {
      // Third case is switch trigger while sidebar is open.
      const previousTriggerId = secondDrawer.dataset?.triggerNodeId ?? null;
      if (previousTriggerId) toggleOpenClassOnClick(previousTriggerId, true);
      setTitle(trigger.dataset.nodeTitle);
      secondDrawer.setAttribute('data-trigger-node-id', triggerId);
      toggleOpenClassOnClick(triggerId);
      Drupal.displayBuilder.LocalStorageManager.set(
        'secondDrawerActiveNodeId',
        triggerId,
        builder.id,
      );
    }
  }
};

/**
 * Initialize the Library/Tree and Settings sidebars in the builder.
 *
 * @param {HTMLElement} builder
 *   The Display Builder element.
 *
 * @listens event:keydown
 */
Drupal.displayBuilder.initDrawer = (builder) => {
  // Sidebar selector constants.
  const FIRST_DRAWER_ID = '#db-first-drawer';
  const SECOND_DRAWER_ID = '#db-second-drawer';

  /**
   * Shared resize handler for sidebars.
   *
   * @param {HTMLElement} sidebar
   *   The sidebar.
   * @param {Function} handleResize
   *   The resize handler function, applying the width for one event.
   * @param {Function} persistWidth
   *   Called once, on mouseup, to persist the final width - not on every
   *   mousemove, since that would mean a synchronous localStorage write
   *   (JSON.stringify + setItem over the whole namespace) on every single
   *   pixel of cursor movement during the drag.
   */
  const handleResizeHandler = (sidebar, handleResize, persistWidth) => {
    const resizeHandler = sidebar.querySelector('.db-sidebar__resize-handle');
    if (!resizeHandler) return;
    let isResizing = false;
    // Raw mousemove fires far more often than the display can repaint, and
    // handleResize() itself does a getBoundingClientRect() read followed by
    // a style.width write - batching to one application per animation frame
    // (rather than one per mousemove) keeps the drag from doing that
    // read/write cycle more often than the screen can actually show it.
    let pendingEvent = null;
    let rafId = null;

    const applyPendingResize = () => {
      rafId = null;
      if (pendingEvent) handleResize(pendingEvent);
    };

    resizeHandler.addEventListener('mousedown', (event) => {
      isResizing = true;
      // The width transition is meant for the collapse/expand toggle - left
      // on during a drag it fights every mousemove update, lagging behind
      // the cursor instead of tracking it 1:1.
      sidebar.classList.add('db-sidebar--resizing');
      document.body.style.cursor = 'ew-resize';
      event.preventDefault();
    });
    document.addEventListener('mousemove', (event) => {
      if (!isResizing) return;
      pendingEvent = event;
      if (rafId === null) {
        rafId = requestAnimationFrame(applyPendingResize);
      }
    });
    document.addEventListener('mouseup', () => {
      if (isResizing) {
        isResizing = false;
        sidebar.classList.remove('db-sidebar--resizing');
        document.body.style.cursor = '';
        if (rafId !== null) {
          cancelAnimationFrame(rafId);
          applyPendingResize();
        }
        pendingEvent = null;
        persistWidth();
      }
    });
  };

  /**
   * First sidebar (Library/Tree) initialization.
   *
   * @return {{element: HTMLElement, collapse: Function}|null}
   *   The sidebar element and a collapse function, or null if not found.
   */
  function initFirstDrawer() {
    const firstDrawer = builder.querySelector(FIRST_DRAWER_ID);
    if (!firstDrawer) return null;

    let startDrawerWidth =
      Drupal.displayBuilder.LocalStorageManager.get('startDrawerWidth') ||
      START_DEFAULT_WIDTH;

    // The sidebar starts collapsed (see display_builder.twig); the inline
    // width is only (re-)applied by expandFirstDrawer() below, so it never
    // overrides the CSS rail-width collapse rule.

    const firstDrawerPanes = builder.querySelectorAll('.db-sidebar__pane');
    let activeFirstDrawerButton = null;

    const isCollapsed = () => firstDrawer.classList.contains('is-collapsed');

    const clearActiveTrigger = () => {
      if (activeFirstDrawerButton) {
        activeFirstDrawerButton.variant = 'default';
        activeFirstDrawerButton = null;
      }
    };

    /**
     * Clear tree selection when the first sidebar is collapsed.
     *
     * @todo move this to panel_tree behavior as sidebar plugin?
     */
    const clearTreeSelection = () => {
      const treeIsland = builder.querySelector('.db-island-tree');
      if (!treeIsland) return;
      builder
        .querySelectorAll('.db-tree--selected')
        .forEach((elt) => elt.classList.remove('db-tree--selected'));
    };

    const collapseFirstDrawer = () => {
      firstDrawer.classList.add('is-collapsed');
      // Remove the inline width so the CSS rail-width rule can apply -
      // an inline style always outranks a stylesheet class rule.
      firstDrawer.style.removeProperty('width');
      Drupal.displayBuilder.setSidebarWidth('start', 0);
      clearActiveTrigger();
      clearTreeSelection();
      // Cleared here (rather than only where the drawer's own trigger
      // button is clicked) so every way of closing the drawer - the Escape
      // key included - keeps this in sync, or a reload would incorrectly
      // reopen a drawer the user explicitly closed.
      Drupal.displayBuilder.LocalStorageManager.remove(
        'firstDrawerActiveTarget',
      );
    };

    const expandFirstDrawer = () => {
      firstDrawer.classList.remove('is-collapsed');
      firstDrawer.style.width = `${startDrawerWidth}px`;
      Drupal.displayBuilder.setSidebarWidth('start', startDrawerWidth);
    };

    /**
     * Handle resize event for the first sidebar.
     *
     * @param {Object} event
     *   The resize event.
     */
    const handleResize = (event) => {
      // event.clientX is a viewport-relative cursor position - subtract the
      // sidebar's own left edge (wherever flex layout placed it) so the
      // computed width matches how far the cursor moved from that edge, not
      // from the true viewport edge.
      const drawerLeft = firstDrawer.getBoundingClientRect().left;
      startDrawerWidth = Math.max(
        MIN_WIDTH,
        Math.min(
          event.clientX - drawerLeft,
          parseInt(window.innerWidth / 1.2, 10),
        ),
      );
      firstDrawer.style.width = `${startDrawerWidth}px`;
      Drupal.displayBuilder.setSidebarWidth('start', startDrawerWidth);
    };

    handleResizeHandler(firstDrawer, handleResize, () =>
      Drupal.displayBuilder.LocalStorageManager.set(
        'startDrawerWidth',
        startDrawerWidth,
      ),
    );

    /**
     * Toggle first sidebar content panes.
     *
     * @param {string|null} showId
     *  The ID of the pane to show.
     */
    const toggleFirstDrawerContent = (showId = null) => {
      firstDrawerPanes.forEach((pane) => {
        if (showId && pane.firstElementChild.id === showId) {
          pane.classList.remove('db-sidebar__pane--hidden');
        } else {
          pane.classList.add('db-sidebar__pane--hidden');
        }
      });
    };

    /**
     * Handle click on first sidebar trigger buttons.
     *
     * @param {HTMLElement} trigger
     *   The trigger button element that was clicked.
     */
    const handleFirstDrawerTriggerClick = (trigger) => {
      if (!isCollapsed() && trigger === activeFirstDrawerButton) {
        collapseFirstDrawer();
        toggleFirstDrawerContent();
        trigger.variant = 'default';
        activeFirstDrawerButton = null;
      } else {
        const showIslandId = `island-${builder.id}-${trigger.dataset?.target}`;
        toggleFirstDrawerContent(showIslandId);
        expandFirstDrawer();
        trigger.variant = 'primary';
        if (activeFirstDrawerButton)
          activeFirstDrawerButton.variant = 'default';
        activeFirstDrawerButton = trigger;
        Drupal.displayBuilder.LocalStorageManager.set(
          'firstDrawerActiveTarget',
          trigger.dataset?.target,
        );
      }
    };

    const firstDrawerButtons = builder.querySelectorAll(
      '[data-open-first-drawer]',
    );
    if (firstDrawerButtons.length > 0) {
      firstDrawerButtons.forEach((button) => {
        // Click is important to allow keyboard click action mapping.
        button.addEventListener('click', () => {
          handleFirstDrawerTriggerClick(button, builder);
        });
      });

      // Restore whichever pane (if any) was open before the last reload.
      const activeTarget = Drupal.displayBuilder.LocalStorageManager.get(
        'firstDrawerActiveTarget',
      );
      const buttonToRestore = [...firstDrawerButtons].find(
        (button) => button.dataset?.target === activeTarget,
      );
      if (buttonToRestore) handleFirstDrawerTriggerClick(buttonToRestore);
    }

    return { element: firstDrawer, collapse: collapseFirstDrawer };
  }

  /**
   * Second sidebar (Settings) initialization.
   *
   * @return {{element: HTMLElement, collapse: Function}|null}
   *   The sidebar element and a collapse function, or null if not found.
   */
  function initSecondDrawer() {
    const secondDrawer = builder.querySelector(SECOND_DRAWER_ID);
    if (!secondDrawer) return null;

    let endDrawerWidth =
      Drupal.displayBuilder.LocalStorageManager.get('endDrawerWidth') ||
      END_DEFAULT_WIDTH;

    // The sidebar starts collapsed (see display_builder.twig); the inline
    // width is only applied by handleSecondDrawer's open() below, so it
    // never overrides the CSS width:0 collapse rule.
    const collapse = () => {
      Drupal.displayBuilder.handleSecondDrawer(builder, null, null, 'close');
      builder
        .querySelectorAll('.db-node-contextual-open')
        .forEach((elt) => elt.classList.remove('db-node-contextual-open'));
    };

    /**
     * Handle resize event for the second sidebar.
     *
     * @param {Object} event
     *   The resize event.
     */
    const handleResize = (event) => {
      // Measure from the sidebar's own right edge (wherever flex layout
      // placed it), not the viewport's, so the computed width matches how
      // far the cursor moved from that edge.
      const drawerRight = secondDrawer.getBoundingClientRect().right;
      endDrawerWidth = Math.max(
        MIN_WIDTH,
        Math.min(
          drawerRight - event.clientX,
          parseInt(window.innerWidth / 1.5, 10),
        ),
      );
      secondDrawer.style.width = `${endDrawerWidth}px`;
      Drupal.displayBuilder.setSidebarWidth('end', endDrawerWidth);
    };

    handleResizeHandler(secondDrawer, handleResize, () =>
      Drupal.displayBuilder.LocalStorageManager.set(
        'endDrawerWidth',
        endDrawerWidth,
      ),
    );

    // Delegate from the stable builder root rather than binding directly on
    // the close button - an HTMX out-of-band swap can replace that button's
    // DOM node, which would silently drop a directly-bound listener.
    builder.addEventListener('click', (event) => {
      if (event.target.closest('#db-second-drawer-close')) {
        collapse();
      }
    });

    // Restore whichever node's settings (if any) were open before the last
    // reload - namespaced per builder.id (unlike firstDrawerActiveTarget's
    // global namespace, @see initFirstDrawer()) because a node id is only
    // meaningful within the specific instance's own tree it came from.
    // Re-clicking the node (rather than calling open() directly) replays
    // the exact same htmx request its hx-on:click already wires up
    // (@see \Drupal\display_builder\HtmxEvents::onInstanceClick()), since
    // that's what actually fetches the settings content itself - open()
    // here only manages the sidebar's own visual state. Deferred a tick -
    // this runs synchronously during Drupal's own behavior-attach cycle,
    // before htmx has necessarily finished processing the page's initial
    // hx-* attributes, so a click reaching this element right now wouldn't
    // yet be seen by htmx at all.
    const activeNodeId = Drupal.displayBuilder.LocalStorageManager.get(
      'secondDrawerActiveNodeId',
      null,
      builder.id,
    );
    if (activeNodeId) {
      setTimeout(() => {
        // A node's own id is mirrored onto other elements that aren't its
        // settings trigger - e.g. its Tree/Wireframe panel counterpart - so
        // scope to data-hx-get, only present on the element
        // HtmxEvents::onInstanceClick() actually wires up for this.
        const nodeElement = builder.querySelector(
          `[data-node-id="${activeNodeId}"][data-hx-get]`,
        );
        if (nodeElement) nodeElement.click();
      });
    }

    return { element: secondDrawer, collapse };
  }

  // Initialize both sidebars and add a shared Escape key handler.
  const firstDrawer = initFirstDrawer();
  const secondDrawer = initSecondDrawer();

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (
      secondDrawer &&
      !secondDrawer.element.classList.contains('is-collapsed')
    ) {
      secondDrawer.collapse();
    } else if (
      firstDrawer &&
      !firstDrawer.element.classList.contains('is-collapsed')
    ) {
      firstDrawer.collapse();
    }
  });
};
