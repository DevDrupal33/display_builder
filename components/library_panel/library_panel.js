/**
 * @file
 * Provides filtering behavior for the Display Builder library islands.
 */

((Drupal, once, debounce) => {
  /**
   * Handles the input event for filtering draggables.
   *
   * @param {HTMLElement} context
   *   The builder element containing dropzone.
   * @param {HTMLElement} input
   *   The input to trigger search on.
   */
  function triggerSearch(context, input) {
    const wrapper = context.getElementById(input.dataset.target);
    if (!wrapper) return;

    if (!input.dataset.elementsSelector) return;
    const elements = wrapper.querySelectorAll(input.dataset.elementsSelector);
    if (!elements) return;

    const query = input.value.trim().toLowerCase();

    const filterGroup = new Set();
    // Store the result of those query once.
    const hiddenElements = wrapper.querySelectorAll(
      '.db-filter-hide-on-search',
    );
    const parentElements = wrapper.querySelectorAll('[data-filter-parent]');

    // Early exit if no query
    if (query.length <= 2) {
      elements.forEach((element) => {
        element.classList.remove('db-library-search-out');
      });
      hiddenElements.forEach((entry) => {
        entry.classList.remove('db-library-search-out');
      });
      parentElements.forEach((entry) => {
        entry.classList.remove('db-library-search-out');
      });
      return;
    }

    elements.forEach((element) => {
      // Use data-keywords as search terms. Fallback to title.
      let match = element.title;
      if (element.dataset.keywords) {
        match = element.dataset.keywords;
      }
      match = match.trim().toLowerCase();
      if (match && match.includes(query)) {
        element.classList.remove('db-library-search-out');
        if (element.dataset.filterChild) {
          filterGroup.add(element.dataset.filterChild);
        }
      } else {
        element.classList.add('db-library-search-out');
      }
    });

    hiddenElements.forEach((entry) => {
      entry.classList.toggle('db-library-search-out', query.length > 0);
    });

    parentElements.forEach((entry) => {
      if (query.length > 0) {
        entry.classList.toggle(
          'db-library-search-out',
          !filterGroup.has(entry.dataset.filterParent),
        );
      } else {
        entry.classList.remove('db-library-search-out');
      }
    });
  }
  /**
   * Drupal behavior for display builder search library.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for display builder search library.
   *
   * @listens shoelace:sl-input
   */
  Drupal.behaviors.displayBuilderLibraryIslands = {
    attach(context) {
      once('dbLibraryFilterInit', '.shoelace-button-search', context).forEach(
        (filterInput) => {
          // Debounce to wait for tipping ad not throw too much search.
          const eventHandler = debounce((event) => {
            triggerSearch(context, event.target);
          }, 300);
          // Specific shoelace event handler.
          // @todo avoid using this kind of specific.
          filterInput.addEventListener('sl-input', eventHandler);
        },
      );
    },
  };
})(Drupal, once, Drupal.debounce);
