/**
 * @file
 * Specific behaviors for the display builder.
 */
((Drupal, once) => {
  /**
   * Disable all links in builder and preview islands.
   *
   * @param {HTMLElement} builder - The builder element to disable links inside
   * @listens event:click
   */
  function disableInsideLinks(builder) {
    if (
      !builder.classList.contains('db-island-builder') &&
      !builder.classList.contains('db-island-preview')
    ) {
      return;
    }
    builder.querySelectorAll('a').forEach((link) => {
      if (link.closest('div').classList === 'contextual') {
        return;
      }
      link.addEventListener('click', (event) => event.preventDefault());
    });
  }

  /**
   * Gets the trigger type from an event.
   *
   * @param {CustomEventPrototype} event
   *   The event object containing detail.target with attributes.
   * @return {string}
   *   The trigger type (e.g. 'click', 'dragend') extracted from hx-trigger attribute.
   *   Returns empty string if no hx-trigger attribute exists.
   */
  function getTrigger(event) {
    let trigger =
      'hx-trigger' in event.detail.target.attributes
        ? event.detail.target.attributes['hx-trigger'].value
        : '';
    // 'click consume' must return 'click'.
    trigger = trigger.split(' ')[0].trim();
    return trigger;
  }

  /**
   * Checks if an event target is a dropzone element.
   *
   * @param {CustomEventPrototype} event - The event object to check
   * @return {boolean} True if the event target has the db-dropzone class
   */
  function isDropzone(event) {
    return event.detail.elt.classList.contains('db-dropzone');
  }

  /**
   * Adds position and instance values to an event's parameters.
   *
   * @param {CustomEventPrototype} event - The event to add values to
   * @return {CustomEventPrototype} The modified event with added parameters
   */
  function addVals(event) {
    const dropzone = event.detail.elt;
    const draggable = event.detail.triggeringEvent.target;
    const position = Array.from(dropzone.children).indexOf(draggable);
    if ('hx-vals' in draggable.attributes) {
      event.detail.parameters = JSON.parse(
        draggable.attributes['hx-vals'].value,
      );
    } else if (draggable.dataset?.instanceId) {
      event.detail.parameters = {
        instance_id: draggable.dataset.instanceId,
      };
    }
    event.detail.parameters.position = position;

    return event;
  }

  /**
   * Sets up event listeners for HTMX requests on a builder element.
   *
   * @param {HTMLElement} builder - The builder element to attach events to
   * @listens htmx:configRequest
   * @listens htmx:beforeRequest
   * @listens htmx:afterRequest
   */
  function alterHtmxEvents(builder) {
    builder.addEventListener('htmx:configRequest', (event) => {
      // Add draggable data & drop position to request.
      if (getTrigger(event) === 'dragend' && isDropzone(event)) {
        event = addVals(event);
      }
    });

    builder.addEventListener('htmx:beforeRequest', (event) => {
      // Class used for opacity until request is finished.
      // Avoid on preview.
      if (!event.target.classList.contains('db-placeholder')) {
        builder.classList.add('db-htmx-before-request');
      }

      // Don't trigger api_instance_get request if already the active instance.
      // Instead open the contextual edit actions.
      const activeInstanceId = builder.getAttribute('data-active-instance');
      const currentPath = event.detail.requestConfig.path;
      if (
        currentPath.endsWith(`/instance/${activeInstanceId}`) &&
        event.detail.requestConfig.verb === 'get'
      ) {
        event.preventDefault();
        builder.classList.remove('db-htmx-before-request');
        // Handle second click to open the modal.
        const editAction = builder.querySelector('[data-menu-action="edit"]');
        if (!editAction || editAction.dataset.ModalIsOpen) return;
        editAction.click();
      }
    });

    builder.addEventListener('htmx:afterRequest', (event) => {
      builder.classList.remove('db-htmx-before-request');
      const url = new URL(event.detail.xhr.responseURL);
      const instances = builder.querySelectorAll('[data-instance-id]');
      Array.from(instances).forEach((instance) => {
        if (instance.attributes['hx-get']?.value === url.pathname) {
          builder.setAttribute(
            'data-active-instance',
            instance.dataset.instanceId,
          );
        }
      });
    });
  }

  /**
   * Initialize the display builder mechanics with drag and drop and htmx.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder functionality.
   */
  Drupal.behaviors.displayBuilder = {
    attach(context, settings) {
      const debug = settings.dbDebug;
      once('dbInit', '.db-display-builder', context).forEach((builder) => {
        alterHtmxEvents(builder);
        Drupal.displayBuilder.initDrawer(builder, debug);
      });
      once('dbIslandInit', '.db-island-view', context).forEach((builder) => {
        disableInsideLinks(builder);
      });
    },
  };
})(Drupal, once);
