/**
 * @param Drupal
 * @param once
 * @param Sortable
 * @file
 * Attaches behaviors for Drupal's Display Builder Draggables.
 */

((Drupal, once, Sortable) => {
  /**
   * Sets up draggable elements within a builder using Sortable.js.
   *
   * Sortable do not support nested, draggables must be flat to reduce the
   * init loop. With placeholder there is a preview of components.
   *
   * @param {HTMLElement} draggableContainer - The element containing draggable collections
   */
  function setDraggable(draggableContainer) {
    if (Sortable.get(draggableContainer)) return;

    const builderId = draggableContainer.dataset.dbId;
    const sortableSettings = {
      ghostClass: 'db-draggable--ghost',
      chosenClass: 'db-draggable--chosen',
      dragClass: 'db-draggable--drag',
      draggable: '.db-placeholder',
      group: {
        name: builderId,
        pull: 'clone',
        put: false,
      },
      animation: 150,
      sort: false,
      onUnchoose(event) {
        // If the item was dropped out of a dropzone it is event.item that
        // stays in the draggable list, still htmx-processed, nothing to do.
        // If it was dropped in, event.clone takes its place: a raw DOM copy
        // htmx never saw, so its hx-* preview attributes are inert until we
        // hand it back - otherwise that library item silently loses its
        // preview for the rest of the session.
        const isInDraggables = event.item.closest('.db-draggables');
        if (!isInDraggables && typeof htmx !== 'undefined') {
          // eslint-disable-next-line no-undef
          htmx.process(event.clone);
        }
      },
      onStart() {
        // Covers the touch/fallback drag path, which never fires the
        // mousedown that js/preview.js hides on.
        Drupal.displayBuilder.hidePreview?.();
        draggableContainer
          .closest(`[id="${builderId}"]`)
          .classList.add('display-builder--on-drag');
      },
      onEnd() {
        draggableContainer
          .closest(`[id="${builderId}"]`)
          .classList.remove('display-builder--on-drag');
      },
    };

    Sortable.create(draggableContainer, sortableSettings);
  }

  /**
   * Enable Display builder Draggables feature.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for Display builder draggable.
   */
  Drupal.behaviors.displayBuilderDraggable = {
    attach(context) {
      once('dbDraggablesInit', '.db-draggables', context).forEach(
        (draggableContainer) => {
          setDraggable(draggableContainer);
        },
      );

      // Same stale-instance cleanup as the dropzones: destroy the Sortable
      // instance of any draggables list htmx removes from the document, or
      // it lingers in SortableJS's module-level registry forever.
      // @see components/dropzone/dropzone.js for the full rationale.
      once('dbDraggablesCleanup', 'body', context).forEach((body) => {
        body.addEventListener('htmx:beforeCleanupElement', (event) => {
          const element = event.target;
          if (element.classList?.contains('db-draggables')) {
            Sortable.get(element)?.destroy();
          }
        });
      });
    },
  };
})(Drupal, once, Sortable);
