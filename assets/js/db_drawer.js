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

  // Handle 'click' type. Main action, when something is clicked in the builder.
  if (type === 'click') {
    const triggerId = trigger.dataset.nodeId || '';
    const triggerNodeId = secondDrawer.dataset?.triggerNodeId;

    if (!secondDrawer.open) {
      secondDrawer.label = trigger.dataset.nodeTitle;
      secondDrawer.setAttribute('data-trigger-node-id', triggerId);
      secondDrawer.show();
    } else if (triggerNodeId === triggerId) {
      secondDrawer.hide();
      secondDrawer.removeAttribute('data-trigger-node-id');
    } else {
      secondDrawer.label = trigger.dataset.nodeTitle;
      secondDrawer.setAttribute('data-trigger-node-id', triggerId);
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

  // Shared resize handler for drawers
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
  // First Drawer initialization
  function initFirstDrawer() {
    const firstDrawer = builder.querySelector(FIRST_DRAWER_ID);
    if (!firstDrawer) return;

    let startDrawerWidth =
      Drupal.displayBuilder.LocalStorageManager.get(
        builder.id,
        'startDrawerWidth',
      ) || null;

    // Reset left margin value for Drupal displace.
    firstDrawer.removeAttribute('data-offset-left');

    const firstDrawerPanes = builder.querySelectorAll(
      '.shoelace-drawer__content_island',
    );
    let activeFirstDrawerButton = null;

    const getDrawerWidth = (drawer) => {
      const size = getComputedStyle(drawer).getPropertyValue('--size').trim();
      return size.endsWith('rem')
        ? remToPx(parseFloat(size))
        : parseFloat(size);
    };

    const adjustMainMarginOnShow = () => {
      const drawerWidth = getDrawerWidth(firstDrawer) || 400;
      if (builder.classList.contains('display-builder--fullscreen')) {
        builder.querySelector('.display-builder__main').style.marginLeft =
          `${drawerWidth}px`;
      }
      firstDrawer.setAttribute('data-offset-left', `${drawerWidth}px`);
      Drupal.displace(true);
    };

    const resetMainMarginOnHide = () => {
      if (builder.classList.contains('display-builder--fullscreen')) {
        builder.querySelector('.display-builder__main').style.marginLeft = '0';
      }
      firstDrawer.removeAttribute('data-offset-left');
      Drupal.displace(true);
    };

    const handleResize = (event) => {
      startDrawerWidth = Math.max(
        200,
        Math.min(event.clientX, parseInt(window.innerWidth / 1.2, 10)),
      );
      firstDrawer.setAttribute('data-offset-left', `${startDrawerWidth}px`);
      Drupal.displace(true);
      if (builder.classList.contains('display-builder--fullscreen')) {
        builder.querySelector('.display-builder__main').style.marginLeft =
          `${startDrawerWidth}px`;
      }
      firstDrawer.style.setProperty('--size', `${startDrawerWidth}px`);
      Drupal.displayBuilder.LocalStorageManager.set(
        builder.id,
        'startDrawerWidth',
        startDrawerWidth,
      );
    };

    handleResizeHandler(firstDrawer, handleResize);

    const onHideResetActiveTrigger = (event) => {
      if (event.target?.id === 'db-first-drawer' && activeFirstDrawerButton) {
        activeFirstDrawerButton.variant = 'default';
        activeFirstDrawerButton = null;
      }
    };

    const toggleFirstDrawerContent = (showId = null) => {
      firstDrawerPanes.forEach((pane) => {
        if (showId && pane.firstElementChild.id === showId) {
          pane.classList.remove('shoelace-drawer__hidden');
        } else {
          pane.classList.add('shoelace-drawer__hidden');
        }
      });
    };

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

    const attachEventListenersToFirstDrawerButtons = () => {
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
    };

    attachEventListenersToFirstDrawerButtons();

    firstDrawer.addEventListener('sl-show', adjustMainMarginOnShow);
    firstDrawer.addEventListener('sl-hide', resetMainMarginOnHide);
    firstDrawer.addEventListener('sl-hide', onHideResetActiveTrigger);

    return firstDrawer;
  }

  // Second Drawer initialization
  function initSecondDrawer() {
    const secondDrawer = builder.querySelector(SECOND_DRAWER_ID);
    if (!secondDrawer) return;

    let endDrawerWidth = 400;

    const handleResize = (event) => {
      endDrawerWidth = Math.max(
        200,
        Math.min(
          window.innerWidth - event.clientX,
          parseInt(window.innerWidth / 1.5, 10),
        ),
      );
      secondDrawer.style.setProperty('--size', `${endDrawerWidth}px`);
    };

    handleResizeHandler(secondDrawer, handleResize);

    return secondDrawer;
  }

  // Initialize both drawers and add Escape key handler
  const firstDrawer = initFirstDrawer();
  const secondDrawer = initSecondDrawer();
  addEscapeKeyHandler(firstDrawer, secondDrawer);
};
