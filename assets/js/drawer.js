/**
 * Self documenting behaviors for managing Drawer in Display Builder.
 */
/* eslint no-use-before-define: 0 */
/* eslint no-unused-expressions: 0 */
/* eslint no-console: 0 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

Drupal.displayBuilder.clearSecondDrawerOpenedFlag = (builder) => {
  builder
    .querySelectorAll('[data-drawer-trigger-opened]')
    .forEach((existingTrigger) => {
      existingTrigger.removeAttribute('data-drawer-trigger-opened');
    });
};

/**
 * Handles the click event for the second drawer trigger button.
 *
 * @param {Object} builder
 *   The builder.
 * @param {Object} trigger
 *   The trigger button element that was clicked.
 * @prop {string} trigger.variant
 *   The current variant of the trigger button (e.g., 'default', 'primary').
 */
Drupal.displayBuilder.handleSecondDrawer = (builder, trigger) => {
  const secondDrawer = builder.querySelector('#db-second-drawer');
  if (!secondDrawer) return;

  Drupal.displayBuilder.clearSecondDrawerOpenedFlag(builder);
  let activeSecondDrawerButton = null;

  if (secondDrawer?.open && trigger === activeSecondDrawerButton) {
    secondDrawer.hide();
    activeSecondDrawerButton = null;
  } else {
    secondDrawer.show();
    activeSecondDrawerButton = trigger;
    // trigger.setAttribute('data-drawer-trigger-opened', true);
  }
};

/**
 * Initialize Drawer in the builder.
 *
 * @param {HTMLElement} builder
 *   The Display Builder element.
 * @param {Boolean} debug
 *   The debug flag.
 */
Drupal.displayBuilder.initDrawer = (builder, debug) => {
  const firstDrawer = builder.querySelector('#db-first-drawer');
  const firstDrawerPanes = builder.querySelectorAll(
    '.db-drawer__content_island',
  );
  const secondDrawer = builder.querySelector('#db-second-drawer');

  let activeFirstDrawerButton = null;
  const activeSecondDrawerButton = null;

  // Attach drawer opening to any button in toolbar.
  const attachEventListenersToFirstDrawerButtons = () => {
    const firstDrawerButtons = builder.querySelectorAll(
      '[data-open-first-drawer]',
    );
    if (firstDrawerButtons.length > 0) {
      firstDrawerButtons.forEach((button) => {
        button.addEventListener('click', () =>
          handleFirstDrawerTriggerClick(button, builder),
        );
      });
    }
  };

  // Attach drawer opening to any button in toolbar.
  attachEventListenersToFirstDrawerButtons();

  /**
   * Helper function to convert rem to px.
   *
   * @param {number} rem
   *   The rem value to convert.
   * @return {number}
   *   The px size.
   */
  const remToPx = (rem) =>
    rem * parseFloat(getComputedStyle(document.documentElement).fontSize);

  // Helper function to get the current drawer width in px.
  const getDrawerWidth = () => {
    if (!firstDrawer) return 0;
    const size = getComputedStyle(firstDrawer)
      .getPropertyValue('--size')
      .trim();
    return size.endsWith('rem') ? remToPx(parseFloat(size)) : parseFloat(size);
  };

  const adjustMainMarginOnShow = () => {
    const drawerWidth = getDrawerWidth() || 400;
    if (builder.classList.contains('db-display-builder--fullscreen')) {
      builder.querySelector('.db-display-builder__main').style.marginLeft =
        `${drawerWidth}px`;
    } else {
      document.body.style.marginLeft = `${drawerWidth}px`;
    }
  };

  const resetMainMarginOnHide = () => {
    if (builder.classList.contains('db-display-builder--fullscreen')) {
      builder.querySelector('.db-display-builder__main').style.marginLeft = '0';
    } else {
      document.body.style.marginLeft = '0';
    }
  };

  const handleResize = (event, drawer, isFirst) => {
    if (!drawer) return;

    const newWidth = isFirst
      ? Math.max(
          200,
          Math.min(event.clientX, parseInt(window.innerWidth / 1.2, 10)),
        )
      : Math.max(
          200,
          Math.min(
            window.innerWidth - event.clientX,
            parseInt(window.innerWidth / 1.5, 10),
          ),
        );

    drawer.style.setProperty('--size', `${newWidth}px`);

    if (isFirst) {
      document.body.style.marginLeft = `${newWidth}px`;
    }
  };

  const handleResizeHandler = (drawer, isFirst) => {
    const resizeHandler = drawer.querySelector('.db-resize-handle');
    if (!resizeHandler) return;

    let isResizing = false;

    resizeHandler.addEventListener('mousedown', (event) => {
      isResizing = true;
      document.body.style.cursor = 'ew-resize';
      event.preventDefault();
    });

    document.addEventListener('mousemove', (event) => {
      if (isResizing) handleResize(event, drawer, isFirst);
    });

    document.addEventListener('mouseup', () => {
      if (isResizing) {
        isResizing = false;
        document.body.style.cursor = '';
      }
    });
  };

  const onHideResetActiveTrigger = (event) => {
    if (event.target?.id === 'db-first-drawer' && activeFirstDrawerButton) {
      activeFirstDrawerButton.variant = 'default';
      activeFirstDrawerButton = null;
    } else if (
      event.target?.id === 'db-second-drawer' &&
      activeSecondDrawerButton
    ) {
      activeSecondDrawerButton.removeAttribute('data-drawer-trigger-opened');
      activeFirstDrawerButton = null;
    }
  };

  const toggleFirstDrawerContent = (showId = null) => {
    firstDrawerPanes.forEach((pane) => {
      if (showId && pane.id === showId) {
        pane.classList.remove('db-drawer__hidden');
        if (debug) console.debug(`[drawer] Showing pane: ${pane.id}`);
      } else {
        pane.classList.add('db-drawer__hidden');
        if (debug) console.debug(`[drawer] Hiding pane: ${pane.id}`);
      }
    });
  };

  /**
   * Handles the click event for the first drawer trigger button.
   *
   * @param {Object} trigger
   *   The trigger button element that was clicked.
   * @prop {string} trigger.variant
   *   The current variant of the trigger button (e.g., 'default', 'primary').
   */
  const handleFirstDrawerTriggerClick = (trigger) => {
    if (firstDrawer.open && trigger === activeFirstDrawerButton) {
      if (debug) console.debug('[drawer] first Drawer open: hide');
      firstDrawer.hide();
      toggleFirstDrawerContent();
      trigger.variant = 'default';
      activeFirstDrawerButton = null;
    } else {
      if (debug) console.debug('[drawer] first Drawer hidden: open');
      const showIslandId = `island-${builder.id}-${trigger.dataset?.target}`;
      toggleFirstDrawerContent(showIslandId);

      firstDrawer.show();
      trigger.variant = 'primary';

      if (activeFirstDrawerButton) activeFirstDrawerButton.variant = 'default';
      activeFirstDrawerButton = trigger;

      // Push the content for the first sidebar.
      const drawerWidth = getDrawerWidth() || 400;
      document.body.style.marginLeft = `${drawerWidth}px`;
    }
  };

  // Init the first drawer.
  if (firstDrawer) {
    firstDrawer.addEventListener('sl-show', adjustMainMarginOnShow);
    firstDrawer.addEventListener('sl-hide', resetMainMarginOnHide);
    firstDrawer.addEventListener('sl-hide', onHideResetActiveTrigger);

    handleResizeHandler(firstDrawer, true);
  }

  // Init the second drawer.
  if (secondDrawer) {
    secondDrawer.addEventListener('sl-hide', onHideResetActiveTrigger);

    handleResizeHandler(secondDrawer, false);
  }

  // Add Escape key support, as we use shoelace contained drawer version.
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
