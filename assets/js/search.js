/**
 * @file
 * Specific builder search for the display builder.
 */
/* eslint no-use-before-define: 0 */
/* eslint no-console: 0 */

((Drupal, debounce, once) => {
  /**
   * The minimum query length to trigger search.
   *
   * @type {number}
   */
  const minimumQueryLength = 0;

  /**
   * The class to hide elements during search.
   *
   * @type {string}
   */
  const hideClass = 'db-library-search-hide';

  /**
   * The class of elements to hide during search.
   *
   * @type {string}
   */
  const librarySearchHideClass = 'db-filter-hide-on-search';

  /**
   * Drupal behavior for display builder search.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior.
   *
   * @listens shoelace:sl-input
   */
  Drupal.behaviors.builderSearchBehaviors = {
    attach(context, settings) {
      const debug = settings?.dbDebug ?? false;

      // Search inside Instance form, should be drawer end (right).
      once('dbSearch', '.db-search-instance', context).forEach((input) => {
        // Debounce to wait for tipping ad not throw too much search.
        const eventHandler = debounce((event) => {
          triggerInstanceSearch(context, event.target);
        }, 300);
        // Specific shoelace event handler.
        input.addEventListener('sl-input', eventHandler);
      });

      // Search for the library, should be drawer start (left).
      // @see components/library_panel/library_panel.twig
      once('dbLibrarySearch', '.db-search-library', context).forEach(
        (filterInput) => {
          // Debounce to wait for tipping ad not throw too much search.
          const eventHandler = debounce((event) => {
            triggerLibrarySearch(context, event.target, debug);
          }, 300);
          filterInput.addEventListener('sl-input', eventHandler);
        },
      );
    },
  };

  /**
   * Handles the input event for filtering draggables.
   *
   * @param {HTMLElement} element
   *   The element containing the search input and results to filter.
   * @param {HTMLElement} input
   *   The input to trigger search on.
   * @param {boolean} debug
   *   The debug flag.
   */
  const triggerLibrarySearch = (element, input, debug) => {
    if (!input.dataset?.searchContainerId) return;
    const containerId = input.dataset.searchContainerId;

    if (!input.dataset?.elementsSelector) return;
    const selector = input.dataset.elementsSelector;

    const elements = element.querySelectorAll(`#${containerId} ${selector}`);
    if (!elements) {
      if (debug)
        console.warn(`No elements to search in: ${containerId} ${selector}`);
      return;
    }

    const parentList = element.querySelector(`#${containerId}`);

    // If no query, reset search.
    const query = input.value.trim().toLowerCase();
    if (query.length <= minimumQueryLength) {
      resetLibrarySearch(parentList);
      return;
    }

    const filterGroup = new Set();

    elements.forEach((elt) => {
      // Use data-keywords as search terms. Fallback to inner text.
      let match = elt.textContent;
      if (elt.dataset.keywords) {
        match = elt.dataset.keywords;
      }
      match = match.trim().toLowerCase();
      if (match && match.includes(query)) {
        elt.classList.remove(hideClass);
        if (elt.dataset.filterChild) {
          filterGroup.add(elt.dataset.filterChild);
        }
      } else {
        elt.classList.add(hideClass);
      }
    });

    // Get the list of elements to hide on search.
    const elementsToHideWhenSearch = parentList.querySelectorAll(
      `.${librarySearchHideClass}`,
    );
    if (elementsToHideWhenSearch.length !== 0) {
      elementsToHideWhenSearch.forEach((elt) => {
        elt.classList.toggle(hideClass, query.length > 0);
      });
    }

    // Collect section titles that we keep after filtering (for 'Variants' tab).
    const sectionElements = parentList.querySelectorAll(
      '[data-search-section]',
    );
    if (sectionElements.length === 0) return;

    sectionElements.forEach((elt) => {
      if (query.length > 0) {
        elt.classList.toggle(
          hideClass,
          !filterGroup.has(elt.dataset.searchSection),
        );
      } else {
        elt.classList.remove(hideClass);
      }
    });
  };

  /**
   * Reset the library search by removing hide class.
   *
   * @param {HTMLElement} parent
   *   The parent element containing the elements to reset.
   */
  const resetLibrarySearch = (parent) => {
    const elements = parent.querySelectorAll(`.${hideClass}`);
    if (elements.length === 0) return;
    elements.forEach((elt) => {
      elt.classList.remove(hideClass);
    });
  };

  /**
   * Sends a search event each time we have an input.
   *
   * @param {HTMLElement} element
   *   The element containing the search input and results to filter.
   * @param {HTMLElement} input
   *   The input to trigger search on.
   */
  const triggerInstanceSearch = (element, input) => {
    const query = input.value.trim().toLowerCase();
    // Clear or delete input.
    if (query.length <= minimumQueryLength) {
      element
        .querySelector('.db-form')
        .classList.remove('db-form-search-found');
      element.querySelectorAll('.db-form form > details').forEach((elt) => {
        elt.removeAttribute('open');
        elt.style.display = 'block';
      });
      return;
    }

    // Hide everything first and reset found elements.
    element.querySelectorAll('.db-form form > details').forEach((elt) => {
      elt.setAttribute('open', true);
      elt.style.display = 'none';
    });
    element.querySelectorAll('.db-element-search-in').forEach((elt) => {
      elt.classList.remove('db-element-search-in');
    });

    // Selector for ui styles and ui skins forms.
    const elements = element.querySelectorAll(
      `.db-form form details .fieldset-legend,
      .db-form form details fieldset legend,
      .db-form form details > div > label
      `,
    );
    let found = false;
    elements.forEach((elt) => {
      const match = elt.textContent.trim().toLowerCase();
      let matchElement = null;
      let matchParent = null;
      if (match.includes(query)) {
        found = true;
        if (
          elt.parentElement.parentElement.tagName === 'FIELDSET' ||
          elt.parentElement.parentElement.tagName === 'DETAILS'
        ) {
          matchParent = elt.parentElement.parentElement;
          matchElement = elt.parentElement;
        }
        if (
          elt.parentElement.parentElement.parentElement.tagName ===
            'FIELDSET' ||
          elt.parentElement.parentElement.parentElement.tagName === 'DETAILS'
        ) {
          matchParent = elt.parentElement.parentElement.parentElement;
          matchElement = elt.parentElement.parentElement;
        }
      }
      if (matchParent) {
        matchParent.setAttribute('open', true);
        matchParent.style.display = 'block';
        matchElement.classList.add('db-element-search-in');
      }
    });

    const form = element.querySelector('.db-form');
    if (!form) return;

    if (found) {
      form.classList.add('db-form-search-found');
    } else {
      form.classList.remove('db-form-search-found');
    }
  };
})(Drupal, Drupal.debounce, once);
