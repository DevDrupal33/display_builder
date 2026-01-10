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
   * For cross-iframe drag-and-drop, we use setData to pass source info
   * that can be read by the build iframe's dropzones.
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
      // Set data for cross-iframe drag-and-drop.
      setData(dataTransfer, dragEl) {
        const sourceData = {
          sourceId: dragEl.dataset.dbSourceId || dragEl.dataset.sourceId,
          sourceType: dragEl.dataset.dbSourceType || 'component',
          componentId: dragEl.dataset.dbComponentId || dragEl.dataset.componentId,
          blockId: dragEl.dataset.dbBlockId || dragEl.dataset.blockId,
          presetId: dragEl.dataset.dbPresetId || dragEl.dataset.presetId,
          builderId,
        };
        dataTransfer.setData('text/plain', JSON.stringify(sourceData));
        dataTransfer.setData('application/x-display-builder', JSON.stringify(sourceData));
      },
      onUnchoose(event) {
        // If selected is dropped out of dropzone, it is the event.item that
        // stay in the draggable list. We do nothing to keep the preview on.
        // If set in the dropzone, the event.clone become the one in the
        // draggables list. Then we need to pass again through htmx to get the
        // preview.
        const isInDraggables = event.item.closest('.db-draggables');
        if (!isInDraggables && typeof htmx !== 'undefined') {
          // eslint-disable-next-line no-undef
          htmx.process(event.clone);
        }
      },
      onStart() {
        // Quick fix to hide preview in case it get stuck on visible.
        const preview = document.querySelector('.db-preview');
        if (preview) {
          preview.style.display = 'none';
        }
        draggableContainer
          .closest(`[id="${builderId}"]`)
          .classList.add('display-builder--onDrag');
      },
      onEnd() {
        draggableContainer
          .closest(`[id="${builderId}"]`)
          .classList.remove('display-builder--onDrag');
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
    },
  };
})(Drupal, once, Sortable);
