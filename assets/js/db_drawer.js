/**
 * Self documenting behaviors for managing Drawer in Display Builder.
 */
/* eslint no-use-before-define: 0 */
/* eslint no-unused-expressions: 0 */
/* eslint no-console: 0 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

/**
 * Handles the click event for the second drawer trigger button.
 *
 * Allow open and close second drawer based on a clicked element in the builder,
 * layers or tree. Second click will close the drawer. Label of the drawer is
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

  // Handle 'close' type. Used when contextual menu > delete is used.
  if (type === 'close') {
    if (secondDrawer.open) {
      secondDrawer.hide();
      secondDrawer.removeAttribute('data-trigger-node-id');
      secondDrawer.label = Drupal.t('Settings');
    }
    return;
  }

  // Handle 'dragend' type. Used when a block or component is moved from the
  // library. We ignore move from inside.
  if (type === 'dragend') {
    // Only act if drawer is open and no nodeId is present.
    if (
      secondDrawer.open &&
      !(
        event.target.dataset?.nodeId ||
        secondDrawer.getAttribute('data-trigger-node-id') === ''
      )
    ) {
      secondDrawer.label =
        event.target.dataset?.nodeTitle ?? Drupal.t('Settings');
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
      if (secondDrawer.open && !forceRemove) {
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

    // First case is opening a closed drawer.
    if (!secondDrawer.open) {
      secondDrawer.label = trigger.dataset.nodeTitle;
      secondDrawer.setAttribute('data-trigger-node-id', triggerId);
      secondDrawer.show();
      toggleOpenClassOnClick(triggerId);
    } else if (triggerNodeId === triggerId) {
      // Second case is closing the drawer.
      secondDrawer.hide();
      secondDrawer.removeAttribute('data-trigger-node-id');
      toggleOpenClassOnClick(triggerId);
    } else {
      // Third case is switch trigger while drawer is open.
      const previousTriggerId = secondDrawer.dataset?.triggerNodeId ?? null;
      if (previousTriggerId) toggleOpenClassOnClick(previousTriggerId, true);
      secondDrawer.label = trigger.dataset.nodeTitle;
      secondDrawer.setAttribute('data-trigger-node-id', triggerId);
      toggleOpenClassOnClick(triggerId);
    }
  }
};

/**
 * Initialize Drawer in the builder.
 *
 * @param {HTMLElement} builder
 *   The Display Builder element.
 *
 * @listens event:mouseup
 */
Drupal.displayBuilder.initDrawer = (builder) => {
  // Drawer selector constants
  const FIRST_DRAWER_ID = '#db-first-drawer';
  const SECOND_DRAWER_ID = '#db-second-drawer';

  // Common utility: convert rem to px
  const remToPx = (rem) =>
    rem * parseFloat(getComputedStyle(document.documentElement).fontSize);

  // Common utility: Escape key handler for both drawers
  /**
   * Manage Escape key on drawers.
   *
   * @param {HTMLElement} firstDrawer
   *   The first drawer.
   * @param {HTMLElement} secondDrawer
   *   The second drawer.
   */
  const addEscapeKeyHandler = (firstDrawer, secondDrawer) => {
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        if (secondDrawer && secondDrawer.open) {
          secondDrawer.hide();
        } else if (firstDrawer && firstDrawer.open) {
          firstDrawer.hide();
        }
      }
    });
  };

  /**
   * Shared resize handler for drawers.
   *
   * @param {HTMLElement} drawer
   *   The drawer.
   * @param {HTMLElement} handleResize
   *   The resize handler function.
   */
  const handleResizeHandler = (drawer, handleResize) => {
    const resizeHandler = drawer.querySelector('.shoelace-resize-handle');
    if (!resizeHandler) return;
    let isResizing = false;
    resizeHandler.addEventListener('mousedown', (event) => {
      isResizing = true;
      document.body.style.cursor = 'ew-resize';
      event.preventDefault();
    });
    document.addEventListener('mousemove', (event) => {
      if (isResizing) handleResize(event);
    });
    document.addEventListener('mouseup', () => {
      if (isResizing) {
        isResizing = false;
        document.body.style.cursor = '';
      }
    });
  };

  /**
   * First Drawer initialization.
   *
   * @return {HTMLElement|null}
   *   The first drawer element or null if not found.
   */
  function initFirstDrawer() {
    const firstDrawer = builder.querySelector(FIRST_DRAWER_ID);
    if (!firstDrawer) return null;

    let startDrawerWidth =
      Drupal.displayBuilder.LocalStorageManager.get(
        'displayBuilder',
        'startDrawerWidth',
      ) || 400;

    firstDrawer.style.setProperty('--size', `${startDrawerWidth}px`);

    // Reset left margin value for Drupal displace.
    firstDrawer.removeAttribute('data-offset-left');

    const firstDrawerPanes = builder.querySelectorAll(
      '.shoelace-drawer__content_island',
    );
    let activeFirstDrawerButton = null;

    /**
     * Get the drawer width in pixels.
     *
     * @param {HTMLElement} drawer
     *   The drawer element.
     */
    const getDrawerWidth = (drawer) => {
      const size = getComputedStyle(drawer).getPropertyValue('--size').trim();
      return size.endsWith('rem')
        ? remToPx(parseFloat(size))
        : parseFloat(size);
    };

    /**
     * Adjust main margin when first drawer is shown.
     */
    const adjustMainMarginOnShow = () => {
      const drawerWidth = startDrawerWidth;
      if (builder.classList.contains('display-builder--fullscreen')) {
        builder.querySelector('.display-builder__main').style.marginLeft =
          `${drawerWidth}px`;
      }
      firstDrawer.setAttribute('data-offset-left', `${drawerWidth}px`);
      Drupal.displace(true);
    };

    /**
     * Reset main margin when first drawer is hidden.
     */
    const resetMainMarginOnHide = () => {
      if (builder.classList.contains('display-builder--fullscreen')) {
        builder.querySelector('.display-builder__main').style.marginLeft = '0';
      }
      firstDrawer.removeAttribute('data-offset-left');
      Drupal.displace(true);
    };

    /**
     * Handle resize event for the first drawer.
     *
     * @param {HTMLElement} event
     *   The resize event.
     */
    const handleResize = (event) => {
      startDrawerWidth = Math.max(
        250,
        Math.min(event.clientX, parseInt(window.innerWidth / 1.2, 10)),
      );

      if (builder.classList.contains('display-builder--fullscreen')) {
        builder.querySelector('.display-builder__main').style.marginLeft =
          `${startDrawerWidth}px`;
      }
      firstDrawer.style.setProperty('--size', `${startDrawerWidth}px`);
      firstDrawer.setAttribute('data-offset-left', `${startDrawerWidth}px`);
      Drupal.displace(true);
      Drupal.displayBuilder.LocalStorageManager.set(
        'displayBuilder',
        'startDrawerWidth',
        startDrawerWidth,
      );
    };

    handleResizeHandler(firstDrawer, handleResize);

    /**
     * Reset active trigger button when first drawer is closed.
     *
     * @param {Object} event
     *   The event associated.
     */
    const onHideResetActiveTrigger = (event) => {
      if (event?.target?.id === 'db-first-drawer' && activeFirstDrawerButton) {
        activeFirstDrawerButton.variant = 'default';
        activeFirstDrawerButton = null;
      }
    };

    /**
     * Clear tree selection when first drawer is closed.
     *
     * @todo move this to panel_tree behavior as drawer plugin?
     *
     * @param {Object} event
     *   The event associated.
     */
    const clearTreeSelection = (event) => {
      if (event?.target?.id === 'db-first-drawer') {
        const treeIsland = builder.querySelector('.db-island-tree');
        if (!treeIsland) return;
        builder
          .querySelectorAll('.db-tree-selected')
          .forEach((elt) => elt.classList.remove('db-tree-selected'));
      }
    };

    /**
     * Toggle first drawer content panes.
     *
     * @param {string|null} showId
     *  The ID of the pane to show.
     */
    const toggleFirstDrawerContent = (showId = null) => {
      firstDrawerPanes.forEach((pane) => {
        if (showId && pane.firstElementChild.id === showId) {
          pane.classList.remove('shoelace-drawer__hidden');
        } else {
          pane.classList.add('shoelace-drawer__hidden');
        }
      });
    };

    /**
     * Handle click on first drawer trigger buttons.
     *
     * @param {HTMLElement} trigger
     *   The trigger button element that was clicked.
     */
    const handleFirstDrawerTriggerClick = (trigger) => {
      if (firstDrawer.open && trigger === activeFirstDrawerButton) {
        firstDrawer.hide();
        toggleFirstDrawerContent();
        trigger.variant = 'default';
        activeFirstDrawerButton = null;
      } else {
        const showIslandId = `island-${builder.id}-${trigger.dataset?.target}`;
        toggleFirstDrawerContent(showIslandId);
        firstDrawer.show();
        firstDrawer.label = trigger.dataset?.nodeTitle || trigger.innerText;
        trigger.variant = 'primary';
        if (activeFirstDrawerButton)
          activeFirstDrawerButton.variant = 'default';
        activeFirstDrawerButton = trigger;
        const drawerWidth = getDrawerWidth(firstDrawer) || 400;
        firstDrawer.setAttribute(`data-offset-left`, `${drawerWidth}px`);
        Drupal.displace(true);
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
    }

    firstDrawer.addEventListener('sl-show', adjustMainMarginOnShow);
    firstDrawer.addEventListener('sl-hide', resetMainMarginOnHide);
    firstDrawer.addEventListener('sl-hide', onHideResetActiveTrigger);
    firstDrawer.addEventListener('sl-hide', clearTreeSelection);

    return firstDrawer;
  }

  /**
   * Second Drawer initialization.
   *
   * @return {HTMLElement|null}
   *   The second drawer element or null if not found.
   */
  function initSecondDrawer() {
    const secondDrawer = builder.querySelector(SECOND_DRAWER_ID);
    if (!secondDrawer) return null;

    let endDrawerWidth =
      Drupal.displayBuilder.LocalStorageManager.get(
        'displayBuilder',
        'endDrawerWidth',
      ) || 400;

    secondDrawer.style.setProperty('--size', `${endDrawerWidth}px`);

    /**
     * Clear tree selection when first drawer is closed.
     *
     * @todo move this to panel_tree behavior as drawer plugin?
     *
     * @param {Object} event
     *   The event associated.
     */
    const clearConfigSelection = (event) => {
      if (event.target?.id === 'db-second-drawer') {
        builder
          .querySelectorAll('.db-node-contextual-open')
          .forEach((elt) => elt.classList.remove('db-node-contextual-open'));
      }
    };

    /**
     * Handle resize event for the second drawer.
     *
     * @param {HTMLElement} event
     *   The resize event.
     */
    const handleResize = (event) => {
      endDrawerWidth = Math.max(
        150,
        Math.min(
          window.innerWidth - event.clientX,
          parseInt(window.innerWidth / 1.5, 10),
        ),
      );
      secondDrawer.style.setProperty('--size', `${endDrawerWidth}px`);
      Drupal.displayBuilder.LocalStorageManager.set(
        'displayBuilder',
        'endDrawerWidth',
        endDrawerWidth,
      );
    };

    handleResizeHandler(secondDrawer, handleResize);

    secondDrawer.addEventListener('sl-hide', clearConfigSelection);

    return secondDrawer;
  }

  // Initialize both drawers and add Escape key handler
  const firstDrawer = initFirstDrawer();
  const secondDrawer = initSecondDrawer();
  addEscapeKeyHandler(firstDrawer, secondDrawer);
};
