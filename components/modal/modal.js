/**
 * @file
 * Specific behaviors for the display builder.
 */
/* eslint no-use-before-define: 0 */

((Drupal, once) => {
  Drupal.displayBuilder = Drupal.displayBuilder || {};

  /**
   * Initialize display builder dialog specifics.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder dialog functionality.
   */
  Drupal.behaviors.displayBuilderDialog = {
    attach() {
      [...document.getElementsByClassName('db-display-builder')].forEach(
        (builder) => {
          handleDialog(builder);
          handleResizableDialogs(builder);
        },
      );
    },
  };

  /**
   * Class to handle modal resize functionality.
   */
  Drupal.displayBuilder.ModalResizeHandler = class {
    /**
     * Creates a new ModalResizeHandler instance.
     *
     * @param {string} builderId - The builder id
     * @param {HTMLElement} resizeHandler - The resize handler element
     * @param {HTMLElement} sidebar - The sidebar element to resize
     */
    constructor(builderId, resizeHandler, sidebar) {
      this.builderId = builderId;
      this.resizeHandler = resizeHandler;
      this.sidebar = sidebar;

      // Some default values.
      this.throttleDelay = 16;

      // Get CSS variables
      const style = getComputedStyle(document.documentElement);
      this.width =
        parseInt(style.getPropertyValue('--db-modal-sidebar-width'), 10) || 368;
      this.minWidth =
        parseInt(style.getPropertyValue('--db-modal-sidebar-min-width'), 10) ||
        200;
      this.maxWidth =
        parseInt(style.getPropertyValue('--db-modal-sidebar-max-width'), 10) ||
        window.innerWidth * 0.8;

      // State variables
      this.isResizing = false;
      this.x = 0;
      this.width = 0;
      this.lastUpdate = 0;
      this.rafId = null;

      // Add GPU acceleration class
      this.sidebar.classList.add('db-gpu');

      // Bind event handlers
      this.boundMousedownHandler = this.handleMouseDown.bind(this);
      this.boundMousemoveHandler = this.handleMouseMove.bind(this);
      this.boundMouseupHandler = this.handleMouseUp.bind(this);

      // Initialize
      this.init();
    }

    /**
     * Initialize the resize handler.
     * @listens event:mousedown
     * @listens event:resize
     */
    init() {
      if (!this.resizeHandler || !this.sidebar) {
        // eslint-disable-next-line no-console
        console.warn('Modal resize handler: Required elements not found');
        return;
      }

      this.resizeHandler.addEventListener(
        'mousedown',
        this.boundMousedownHandler,
      );
      this.resizeHandler.style.cursor = 'col-resize';

      // Set initial width constraints
      this.updateSidebarConstraints();

      // Add window resize listener to update constraints
      this.boundWindowResizeHandler = this.updateSidebarConstraints.bind(this);
      window.addEventListener('resize', this.boundWindowResizeHandler);
    }

    /**
     * Update sidebar width constraints based on window size.
     */
    updateSidebarConstraints() {
      this.maxWidth = Math.min(window.innerWidth * 0.8, this.maxWidth);
    }

    /**
     * Handle mouse down event.
     *
     * @param {MouseEvent} event - The mouse down event
     * @listens event:mousedown
     * @listens event:mouseup
     */
    handleMouseDown(event) {
      event.preventDefault();
      this.isResizing = true;
      this.x = event.clientX;
      const sidebarWidth = window.getComputedStyle(this.sidebar).width;
      this.width = parseInt(sidebarWidth, 10);

      document.addEventListener('mousemove', this.boundMousemoveHandler);
      document.addEventListener('mouseup', this.boundMouseupHandler);

      // Add a resize class to disable transitions during resize
      // @todo ths is never removed?
      this.sidebar.classList.add('db-modal--is-resizing');
      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';
      document.body.classList.remove('db-modal-content-transition');
    }

    /**
     * Handle mouse move event with throttling.
     *
     * @param {MouseEvent} event - The mouse move event
     */
    handleMouseMove(event) {
      if (!this.isResizing) return;

      const now = Date.now();
      if (now - this.lastUpdate < this.throttleDelay) return;

      const dx = event.clientX - this.x;
      const newWidth = Math.max(
        this.minWidth,
        Math.min(this.maxWidth, this.width + dx),
      );

      // Cancel any pending animation frame
      if (this.rafId) {
        cancelAnimationFrame(this.rafId);
      }

      // Schedule the update
      this.rafId = requestAnimationFrame(() => {
        this.sidebar.style.width = `${newWidth - 5}px`;
        document.body.classList.remove('db-modal-content-transition');
        document.body.style.marginLeft = `${newWidth}px`;
        Drupal.displayBuilder.LocalStorageManager.set(
          this.builderId,
          `dialogWidth.${this.sidebar.id}`,
          newWidth,
        );
        this.rafId = null;
        const main = document.querySelector(
          '.db-display-builder--fullscreen .db-display-builder__main',
        );
        if (!main) return;
        main.style.marginLeft = `${newWidth}px`;
      });

      this.lastUpdate = now;
    }

    /**
     * Handle mouse up event.
     *
     * @listens event:mouseup
     * @listens event:mousemove
     */
    handleMouseUp() {
      this.isResizing = false;
      document.removeEventListener('mouseup', this.boundMouseupHandler);
      document.removeEventListener('mousemove', this.boundMousemoveHandler);

      // Remove resize class to re-enable transitions
      this.sidebar.classList.remove('db-modal--is-resizing');
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
    }

    /**
     * Clean up event listeners.
     */
    destroy() {
      // Cancel any pending animation frame
      if (this.rafId) {
        cancelAnimationFrame(this.rafId);
      }

      this.resizeHandler.removeEventListener(
        'mousedown',
        this.boundMousedownHandler,
      );
      window.removeEventListener('resize', this.boundWindowResizeHandler);
      document.removeEventListener('mouseup', this.boundMouseupHandler);
      document.removeEventListener('mousemove', this.boundMousemoveHandler);

      // Remove GPU acceleration class
      this.sidebar.classList.remove('db-gpu');

      // Reset styles
      this.resizeHandler.style.cursor = '';
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
    }
  };

  /**
   * Handle the initialization of resizable dialogs.
   *
   * @param {HTMLElement} builder - The builder element containing the dialogs.
   */
  function handleResizableDialogs(builder) {
    const resizableDialogs = builder.querySelectorAll('.db-modal--resizable');

    once('dbModalResize', resizableDialogs).forEach((dialog) => {
      const resizeHandler = dialog.querySelector('.db-modal--resize-handler');

      if (resizeHandler) {
        // eslint-disable-next-line no-new
        new Drupal.displayBuilder.ModalResizeHandler(
          builder.id,
          resizeHandler,
          dialog,
        );
      }
    });
  }

  /**
   * Helper to set the offcanvas width and body left margin.
   *
   * @param {string} builderId - The builder id
   * @param {string} dialogId - The dialog id
   * @param {HTMLElement} dialog - The dialog element
   * @param {bool} transition - Set transition or not
   */
  function setOffcanvasWidth(builderId, dialogId, dialog, transition) {
    const width =
      Drupal.displayBuilder.LocalStorageManager.get(
        builderId,
        `dialogWidth.${dialogId}`,
      ) ||
      window
        .getComputedStyle(document.body)
        .getPropertyValue('--db-modal-sidebar-width')
        .replace('px', '');

    dialog.style.width = `${width - 5}px`;
    if (transition) document.body.classList.add('db-modal-content-transition');
    document.body.style.marginLeft = `${width}px`;

    const main = document.querySelector(
      '.db-display-builder--fullscreen .db-display-builder__main',
    );
    if (!main) return;
    if (transition) main.classList.add('db-modal-content-transition');
    main.style.marginLeft = `${width}px`;
  }

  /**
   * Handle the button click event to toggle the dialog.
   *
   * @param {Event} event - The click event triggered by the button.
   * @param {HTMLElement} builder - The builder element to disable links inside.
   *
   * @see components/button/button.twig
   */
  function handleButtonClick(event, builder) {
    let button = event.target;
    // Because of icon we can click on the button or the icon, to be sure to act
    // on the dialog we lookup for button close to the icon.
    if (!button.classList.contains('db-button')) {
      button = button.closest('.db-button');
    }
    if (!button) return;

    const dialogType = button.dataset.modalType ?? null;

    let selector = `dialog#${button.dataset.modalTarget}`;
    if (dialogType === 'offcanvas') {
      selector += '-sidebar';
    }
    const dialog = builder.querySelector(selector);
    if (!dialog) {
      return;
    }

    const dialogId = dialog.id;
    // Active the button style for offcanvas.
    if (dialogType === 'offcanvas') {
      builder
        .querySelectorAll('.db-button--offcanvas')
        .forEach((buttonOffcanvas) => {
          if (buttonOffcanvas.id !== button.id) {
            buttonOffcanvas.setAttribute('variant', 'default');
          }
        });
    }

    if (dialog.open) {
      dialog.classList.remove('db-modal--transition-opening');
      dialog.close();

      button.setAttribute('variant', 'default');
      button.removeAttribute('data-modal-is-open');

      Drupal.displayBuilder.LocalStorageManager.remove(
        builder.id,
        `dialogOpen.${dialogType}`,
      );
      if (dialogType === 'offcanvas') {
        // Reset the body left margin.
        document.body.style.marginLeft = '';
        // specific fullscreen case.
        const main = document.querySelector(
          '.db-display-builder--fullscreen .db-display-builder__main',
        );
        if (!main) return;
        main.style.marginLeft = '';
      }
    } else {
      // Specific use case for remove instance button, we add an attribute to
      // ensure it does not open the dialog when clicked.

      if (button.dataset.closeOnly) {
        return;
      }
      dialog.classList.add('db-modal--transition-opening');
      dialog.show();

      button.setAttribute('variant', 'primary');
      button.setAttribute('data-modal-is-open', true);

      Drupal.displayBuilder.LocalStorageManager.set(
        builder.id,
        `dialogOpen.${dialogType}`,
        dialogId,
      );
      if (dialogType === 'offcanvas') {
        builder.querySelectorAll('.db-modal').forEach((modal) => {
          if (
            modal.id !== dialogId &&
            modal.classList.contains('db-modal--sidebar')
          ) {
            modal.close();
          }
        });

        setOffcanvasWidth(builder.id, dialogId, dialog, true);
      }
    }
  }

  /**
   * Restore dialog state from local storage.
   *
   * @param {HTMLElement} button - The button element
   * @param {HTMLElement} builder - The builder element
   */
  function restoreDialogState(button, builder) {
    const dialogOpen = Drupal.displayBuilder.LocalStorageManager.get(
      builder.id,
      'dialogOpen.offcanvas',
    );
    if (!dialogOpen) {
      return;
    }

    const dialogId = `${button.dataset.modalTarget}-sidebar`;
    if (dialogId !== dialogOpen) {
      return;
    }

    const dialog = builder.querySelector(`#${dialogId}`);
    if (!dialog) {
      return;
    }

    dialog.show();
    button.setAttribute('variant', 'primary');

    setOffcanvasWidth(builder.id, dialogId, dialog, false);
  }

  /**
   * Handle the initialization of off-canvas buttons.
   *
   * @param {HTMLElement} builder - The builder element containing the buttons.
   * @listens event:click
   */
  function handleDialog(builder) {
    const buttons = builder.querySelectorAll('.db-button[data-modal-target]');
    once('dbModalButton', buttons).forEach((button) => {
      button.addEventListener('click', (event) =>
        handleButtonClick(event, builder),
      );

      // Restore dialog state from local storage
      restoreDialogState(button, builder);
    });
  }
})(Drupal, once);
