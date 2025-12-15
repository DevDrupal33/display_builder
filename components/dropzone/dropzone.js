/**
 * @param Drupal
 * @param once
 * @param Sortable
 * @file
 * Attaches behaviors for Drupal's Display Builder Dropzone.
 */

((Drupal, once, Sortable) => {
  /**
   * Set up sortable dropzone for the display builder.
   *
   * @param {HTMLElement} dropzoneRoot - The element containing dropzone.
   */
  function setDropzone(dropzoneRoot) {
    const builderId = dropzoneRoot.dataset.dbId;
    const sortableSettings = {
      // Add custom classes to style precisely.
      // @see components/dropzone/dropzone.css
      ghostClass: 'db-dropzone--ghost',
      chosenClass: 'db-dropzone--chosen',
      dragClass: 'db-dropzone--drag',
      // https://sortablejs.github.io/Sortable/#thresholds
      animation: 150,
      direction: 'vertical',
      swapThreshold: 0.65,
      // Empty insert threshold to avoid flickering on first drag into a slot.
      emptyInsertThreshold: 0,
      // fallbackOnBody: true,
      // fallbackTolerance: 50,
      group: {
        name: builderId,
        pull: true,
        put: true,
      },
      scroll: true,
      scrollSensitivity: 100,
      scrollSpeed: 30,
      onStart() {
        dropzoneRoot
          .closest(`[id="${builderId}"]`)
          .classList.add('display-builder--onMove');
      },
      onEnd() {
        dropzoneRoot
          .closest(`[id="${builderId}"]`)
          .classList.remove('display-builder--onMove');
      },
    };

    // Init the dropzone root itself.
    if (!Sortable.get(dropzoneRoot)) {
      Sortable.create(dropzoneRoot, sortableSettings);
    }

    const dropzoneList = dropzoneRoot.getElementsByClassName('db-dropzone');
    const dropzoneLength = dropzoneList.length;
    if (!dropzoneLength || dropzoneLength === 0) return;

    for (let i = 0; i < dropzoneLength; i++) {
      const dropzone = dropzoneList[i];
      if (!Sortable.get(dropzone)) {
        Sortable.create(dropzone, sortableSettings);
      }
    }
  }

  /**
   * Enable Display builder Dropzone feature.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for Display builder dropzone.
   */
  Drupal.behaviors.displayBuilderDropzone = {
    attach(context) {
      once('dbDropzone', '.db-dropzone--root', context).forEach(
        (dropzoneRoot) => {
          setDropzone(dropzoneRoot);
          // After swap need to apply on updated panel.
          dropzoneRoot.addEventListener('htmx:oobAfterSwap', (event) => {
            if (event.detail.target.classList.contains('db-dropzone')) {
              setDropzone(dropzoneRoot);
            }
          });
        },
      );
    },
  };
})(Drupal, once, Sortable);
