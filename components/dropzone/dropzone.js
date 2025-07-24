/**
 * @file
 * Attaches behaviors for Drupal's Display Builder Dropzone.
 */

((Drupal, once) => {
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
      swapThreshold: 0.5,
      group: {
        name: builderId,
        pull: true,
        put: true,
      },
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
      // eslint-disable-next-line no-new
      new Sortable(dropzoneRoot, sortableSettings);
    }

    const dropzoneList = dropzoneRoot.getElementsByClassName('db-dropzone');
    const dropzoneLength = dropzoneList.length;
    if (!dropzoneLength || dropzoneLength === 0) return;

    for (let i = 0; i < dropzoneLength; i++) {
      const dropzone = dropzoneList[i];
      if (!Sortable.get(dropzone)) {
        // eslint-disable-next-line no-new
        new Sortable(dropzone, sortableSettings);
      }
    }
  }

  /**
   * Enable Display builder Dropzone feature.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.displayBuilderDropzone = {
    attach(context) {
      once('dbDropzone', '.db-dropzone--root', context).forEach(
        (dropzoneRoot) => {
          setDropzone(dropzoneRoot);
        },
      );
    },
  };
})(Drupal, once);
