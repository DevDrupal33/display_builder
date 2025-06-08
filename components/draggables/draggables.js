/**
 * @file
 * Attaches behaviors for Drupal's Display Builder Draggables.
 */

((Drupal, once) => {
  /**
   * Sets up draggable elements within a builder using Sortable.js.
   *
   * Sortable do not support nested si draggables must be flat to reduce the
   * init loop. With placeholder there is a tippy preview of components, but
   * because sortable clone the element it still contain the tippy, we need to
   * destroy and create the tippy preview.
   *
   * @param {HTMLElement} draggableContainer - The element containing draggable collections
   */
  function setDraggable(draggableContainer) {
    if (Sortable.get(draggableContainer)) return;

    const builderId = draggableContainer.dataset.dbId;
    const sortableSettings = {
      animation: 200,
      ghostClass: 'db-draggable--ghost',
      chosenClass: 'db-draggable--chosen',
      dragClass: 'db-draggable--drag',
      draggable: '.db-placeholder',
      group: {
        name: builderId,
        pull: 'clone',
        put: false,
      },
      swapThreshold: 10,
      emptyInsertThreshold: 10,
      sort: false,
      onUnchoose(event) {
        // If selected is dropped out of dropzone, it is the event.item that
        // stay in the draggable list. We do nothing to keep the tooltip on.
        // If set in the dropzone, the event.clone become the one in the
        // draggables list. Then we need to init the tooltip that was with
        // the event.item, and destroy the one on event.item to avoid activation
        // on the dropzone.
        const isInDraggables = event.item.closest('.db-draggables');
        if (!isInDraggables) {
          Drupal.displayBuilder.initializeTippy(event.clone);
          if (event.item._tippy) {
            event.item._tippy.destroy();
          }
        }
      },
      onStart() {
        draggableContainer
          .closest(`[id="${builderId}"]`)
          .classList.add('db-display-builder--onDrag');
      },
      onEnd() {
        draggableContainer
          .closest(`[id="${builderId}"]`)
          .classList.remove('db-display-builder--onDrag');
      },
    };

    // eslint-disable-next-line no-new
    new Sortable(draggableContainer, sortableSettings);
  }

  /**
   * Enable Display builder Draggables feature.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.displayBuilderDraggable = {
    attach(context) {
      once('dbDraggablesInit', '.db-draggables', context).forEach(
        (draggableContainer) => {
          setDraggable(draggableContainer);
        },
      );
    },
  };
})(Drupal, once);
