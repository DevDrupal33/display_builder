/**
 * @file
 * Specific behaviors for the Display builder.
 */
/* cspell:ignore uidom */
((Drupal, once, { computePosition, offset, shift, flip }) => {
  /**
   * Islands whose nodes support the right-click contextual menu.
   *
   * Kept as one list so a new island (e.g. a future view) only needs
   * adding here once, instead of being silently left without a
   * contextual menu until someone notices.
   *
   * @type {string}
   */
  const CONTEXTUAL_MENU_ISLANDS_SELECTOR =
    '.db-island-builder, .db-island-tree, .db-island-scaffold';

  /**
   * Disable all links in an element.
   *
   * @param {HTMLElement} element
   *   The element that contain links to disable.
   *
   * @listens event:click
   */
  function disableInsideLinks(element) {
    element.querySelectorAll('a[href]:not([href=""])').forEach((link) => {
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
   *   The trigger type (e.g. 'click', 'dragend') extracted from data-hx-trigger attribute.
   *   Returns empty string if no data-hx-trigger attribute exists.
   */
  function getTrigger(event) {
    let trigger =
      'data-hx-trigger' in event.detail.target.attributes
        ? event.detail.target.attributes['data-hx-trigger'].value
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
   * Resolves the element carrying a dragged node's identity attributes.
   *
   * Sortable drags the direct child of the dropzone, but a component
   * rendered with its real output (@see
   * BuilderPanel::buildComponentRealRender()) only carries the node
   * attributes on whichever element its template applies `attributes` to,
   * which is not necessarily its outermost one. Bootstrap's grid row for
   * instance renders `<div class="container"><div class="row"
   * {{ attributes }}>`, leaving the dragged element itself anonymous and
   * the move request with no node to move. The component's own element is
   * always the first identity-bearing descendant in document order - the
   * ones belonging to its slot contents are nested deeper - so fall back
   * to that.
   *
   * @param {HTMLElement} draggable - The element Sortable moved.
   * @return {HTMLElement} The element carrying the node identity.
   */
  function getNodeElement(draggable) {
    const selector = '[data-hx-vals], [data-node-id]';
    return draggable.matches(selector)
      ? draggable
      : draggable.querySelector(selector) || draggable;
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
    const node = getNodeElement(draggable);
    const position = Array.from(dropzone.children).indexOf(draggable);
    if ('data-hx-vals' in node.attributes) {
      event.detail.parameters = JSON.parse(
        node.attributes['data-hx-vals'].value,
      );
    } else if (node.dataset?.nodeId) {
      event.detail.parameters = {
        node_id: node.dataset.nodeId,
      };
    }
    event.detail.parameters.position = position;

    // Builder, Wireframe, and Tree share the same Sortable group, so an
    // existing node can be dragged from one panel's dropzone into
    // another's. Sortable only relocates the DOM node - it never
    // re-renders it - so the dropped element is still wearing whichever
    // panel originally rendered it (@see BuilderPanel::buildNodeAttributes()).
    // Reported so the server can tell a cross-panel move (destination
    // needs a fresh render) apart from a same-panel reorder (destination
    // is already correct).
    if (node.dataset?.islandId) {
      event.detail.parameters.source_island = node.dataset.islandId;
    }

    return event;
  }

  /**
   * Sets up event listeners for HTMX requests on a builder element.
   *
   * @param {HTMLElement} builder
   *   The builder element to attach events to.
   *
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
      const activeNodeId = builder.getAttribute('data-active-instance');
      const currentPath = event.detail.requestConfig.path;
      if (
        currentPath.endsWith(`/node/${activeNodeId}`) &&
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

    // Highlight the active instance after request.
    builder.addEventListener('htmx:afterRequest', (event) => {
      builder.classList.remove('db-htmx-before-request');
      const url = new URL(event.detail.xhr.responseURL);
      const instances = builder.querySelectorAll('[data-node-id]');
      Array.from(instances).forEach((instance) => {
        if (instance.attributes['data-hx-get']?.value === url.pathname) {
          builder.setAttribute('data-active-instance', instance.dataset.nodeId);
        }
      });
    });
  }

  /**
   * Initialize the Display builder mechanics.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for Display builder overall.
   */
  Drupal.behaviors.displayBuilder = {
    attach(context, settings) {
      once('dbInit', '.display-builder', context).forEach((builder) => {
        alterHtmxEvents(builder);
        Drupal.displayBuilder.initDrawer(builder);
      });

      once('dbContextualMenu', '.display-builder', context).forEach(
        (builder) => {
          Drupal.displayBuilder.menuAlterHtmxEvents(builder);
        },
      );

      once(
        'dbIslandDisableLink',
        '.db-island-builder, .db-island-preview',
        context,
      ).forEach((island) => {
        disableInsideLinks(island);
      });

      once('dbIslandInit', CONTEXTUAL_MENU_ISLANDS_SELECTOR, context).forEach(
        (island) => {
          const menu = document.querySelector('.db-menu');
          if (!menu) return;

          const contextualMenu = new Drupal.displayBuilder.ContextualMenu(
            island,
            {
              computePosition,
              offset,
              shift,
              flip,
            },
            menu,
          );

          // Register all plugins from the namespace
          const plugins = Drupal.displayBuilder.ContextualMenuPlugin || {};
          Object.keys(plugins).forEach((key) => {
            const plugin = plugins[key];
            // Register only if it's an object and has at least one hook (e.g., onMenuOpen)
            if (
              plugin &&
              typeof plugin === 'object' &&
              typeof plugin.onMenuOpen === 'function'
              // || add other hooks here if needed
            ) {
              contextualMenu.registerPlugin(plugin);
            }
          });
        },
      );
    },
  };

  /**
   * Trigger Drupal behaviors on HTMX load events.
   *
   * Fix required for core HTMX integration and will need some refactor when
   * HTMX in Drupal core is done.
   *
   * @todo refactor when HTMX is in core.
   *
   * @param {CustomEvent} htmxLoadEvent
   *   The HTMX load event.
   */
  function reattachDisplayBuilder(root) {
    Array.from(root.children).forEach(function (element) {
      Drupal.attachBehaviors(element, drupalSettings);
    });
  }

  function triggerDrupalBehaviorsFromHtmxEvent(htmxLoadEvent) {
    const root =
      htmxLoadEvent.detail.elt?.parentElement?.closest('.display-builder');
    if (!root) {
      return;
    }
    reattachDisplayBuilder(root);
  }

  // Toast messages are appended to the stack and never replaced (@see
  // \Drupal\display_builder\RenderableBuilderTrait::buildError), so a
  // dismissed or auto-closed alert would otherwise linger in the DOM forever,
  // growing an invisible column over the builder. Nested Shoelace elements in
  // the message content bubble this event too, hence the explicit check that
  // the target really is a message sitting in the stack.
  window.addEventListener('sl-after-hide', (event) => {
    const message = event.target;
    if (
      message.matches?.('.db-message') &&
      message.parentElement?.matches('.db-messages')
    ) {
      message.remove();
    }
  });

  // Trigger on HTMX out-of-band swaps.
  window.addEventListener(
    'htmx:oobAfterSwap',
    triggerDrupalBehaviorsFromHtmxEvent,
  );

  // Trigger on htmx:drupal:load, not the native htmx:load - the native event
  // fires as soon as htmx inserts new content, before core's own
  // displace/asset pipeline (@see core/misc/htmx/htmx-assets.js) has merged
  // this response's drupalSettings or finished loading any new JS/CSS it
  // needs. Drupal.behaviors.editor (@see core/modules/editor/js/editor.js)
  // guards its own attach with a one-shot once('editor', ...), so racing it
  // this early can silently consume that one shot before the format's editor
  // settings/assets are ready, permanently leaving a plain <textarea> with
  // no editor ever attached - confirmed live on the Wysiwyg block's text
  // format switch (Plain text -> Basic HTML), intermittently. htmx:drupal:load
  // is core's own custom event, fired only once drupalSettings have been
  // merged and all new assets have finished loading (@see
  // core/misc/htmx/htmx-assets.js's htmx:afterSettle handler) - core's own
  // htmx-behaviors.js already uses it for exactly this reason.
  window.addEventListener(
    'htmx:drupal:load',
    triggerDrupalBehaviorsFromHtmxEvent,
  );

  // Fallback for requests whose triggering element is removed by their own
  // response. Both handlers above rely on htmx:drupal:load, which core fires
  // on detail.elt.parentElement (@see core/misc/htmx/htmx-assets.js); when the
  // response detaches detail.elt - e.g. the Restore / Publish / Revert state
  // buttons, which vanish once the state they toggle is reached - that event
  // is dispatched on a node outside the document and reaches no window
  // listener, so every rebuilt panel keeps its now-stale behaviors (dead
  // dropzones, contextual menus, keyboard shortcuts...) until a full reload.
  //
  // Record each builder request's source element at htmx:beforeSend (still
  // attached, still carrying the xhr), then at htmx:afterRequest - which fires
  // and still carries that same xhr even when the source is gone - re-attach
  // the whole builder if the source has since left the document. This is
  // scoped to exactly the self-removing-source case (source no longer
  // connected), so it never runs for the WYSIWYG text-format form swaps the
  // htmx:drupal:load timing above deliberately guards: those keep their source
  // in place and go through the normal, asset-aware path unchanged.
  const requestSources = new WeakMap();
  window.addEventListener('htmx:beforeSend', (event) => {
    const source = event.detail?.elt;
    if (event.detail?.xhr && source?.closest?.('.display-builder')) {
      requestSources.set(event.detail.xhr, source);
    }
  });
  window.addEventListener('htmx:afterRequest', (event) => {
    const xhr = event.detail?.xhr;
    if (!xhr) {
      return;
    }
    const source = requestSources.get(xhr);
    requestSources.delete(xhr);
    if (source && !source.isConnected) {
      document
        .querySelectorAll('.display-builder')
        .forEach(reattachDisplayBuilder);
    }
  });
})(Drupal, once, FloatingUIDOM);
