/**
 * @file
 * Specific builder search for the display builder.
 */
/* eslint no-use-before-define: 0 */
/* eslint no-console: 0 */

((Drupal, debounce, once) => {
  Drupal.behaviors.builderSearchBehaviors = {
    attach(context, settings) {
      const debug = settings?.dbDebug ?? false;

      once('dbSearch', '.db-search-contextual', context).forEach((input) => {
        // Debounce to wait for tipping ad not throw too much search.
        const eventHandler = debounce((event) => {
          triggerContextualSearch(context, event.target);
        }, 300);
        input.addEventListener('sl-input', eventHandler);
      });

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
   *
   * @listen event:sl-input
   */
  const triggerLibrarySearch = (element, input, debug) => {
    if (!input.dataset?.elementsSelector) return;
    const elements = element.querySelectorAll(input.dataset.elementsSelector);
    if (debug)
      console.warn(`No elements to search: ${input.dataset.elementsSelector}`);
    if (!elements) return;

    const query = input.value.trim().toLowerCase();

    const filterGroup = new Set();
    // Store the result of those query once.
    const hiddenElements = element.querySelectorAll(
      '.db-filter-hide-on-search',
    );
    const parentElements = element.querySelectorAll('[data-filter-parent]');

    // Early exit if no query
    if (query.length <= 2) {
      elements.forEach((elt) => {
        elt.classList.remove('db-library-search-out');
      });
      hiddenElements.forEach((entry) => {
        entry.classList.remove('db-library-search-out');
      });
      parentElements.forEach((entry) => {
        entry.classList.remove('db-library-search-out');
      });
      return;
    }

    elements.forEach((elt) => {
      // Use data-keywords as search terms. Fallback to inner text.
      let match = elt.textContent;
      if (elt.dataset.keywords) {
        match = elt.dataset.keywords;
      }
      match = match.trim().toLowerCase();
      if (match && match.includes(query)) {
        elt.classList.remove('db-library-search-out');
        if (elt.dataset.filterChild) {
          filterGroup.add(elt.dataset.filterChild);
        }
      } else {
        elt.classList.add('db-library-search-out');
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
  };

  /**
   * Sends a search event each time we have an input of 2 letters or more.
   *
   * @param {HTMLElement} element
   *   The element containing the search input and results to filter.
   * @param {HTMLElement} input
   *   The input to trigger search on.
   *
   * @listen event:sl-input
   */
  const triggerContextualSearch = (element, input) => {
    const query = input.value.trim().toLowerCase();
    // Clear or delete input.
    if (query.length <= 2) {
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
