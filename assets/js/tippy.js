/**
 * @file
 * Specific behaviors for the display builder.
 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

/**
 * Initialize tippy for an element.
 *
 * @param {HTMLElement} element - The element to initialize tippy on.
 */
Drupal.displayBuilder.initializeTippy = (element) => {
  /* global tippy */
  // @see https://atomiks.github.io/tippyjs/v6/ajax/
  tippy(element, {
    arrow: false,
    placement: 'right',
    maxWidth: 600,
    allowHTML: true,
    delay: [150, 0], // Set out fast to avoid multiple on close items.
    trigger: 'mouseenter',
    // @see tippy.css
    theme: 'db-preview',

    onCreate(instance) {
      instance._isFetching = false;
      instance._content = null;
      instance._error = null;
    },

    onShow(instance) {
      if (instance._isFetching || instance._content || instance._error) {
        instance.hide();
        return;
      }

      instance._isFetching = true;
      if (!element.dataset.previewUrl) {
        instance.hide();
        return;
      }

      fetch(element.dataset.previewUrl)
        .then((response) => response.text())
        .then((text) => {
          // Arbitrary do not show component with less than 100 chars.
          // @todo consider empty component with few markup.
          if (
            element.classList.contains('.db-placeholder-block') &&
            text.trim().length < 100
          ) {
            instance.hide();
            return;
          }
          instance._content = text;
          instance.setContent(text);
          instance.show();
        })
        .catch((error) => {
          instance._error = error;
          instance.setContent(`Request failed: ${error}`);
        })
        .finally(() => {
          instance._isFetching = false;
        });
    },

    onHidden(instance) {
      instance.setContent('');
      instance._content = null;
      instance._error = null;
    },
  });
};
