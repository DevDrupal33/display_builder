/* eslint max-nested-callbacks: 0 */
/**
 * @param Drupal
 * @param once
 * @file
 * Specific behaviors for keyboard mapping.
 *
 * If an element has a data-keyboard attribute with a key value, when the key is
 * pressed, the element is clicked.
 */

((Drupal, once) => {
  /**
   * Drupal behavior for keyboard mapping.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder functionality.
   *
   * @listens event:keydown
   */
  Drupal.behaviors.displayBuilderKeyboard = {
    attach(context) {
      once('dbKeyboardGlobal', '.display-builder', context).forEach(
        (builder) => {
          const keyboardKeys = document.querySelectorAll('[data-keyboard-key]');
          if (!keyboardKeys) return;

          const keyboardMapping = {};
          const keyboardHelp = {};
          keyboardKeys.forEach((elt) => {
            if (!elt.dataset?.keyboardKey) {
              return;
            }
            keyboardMapping[elt.dataset.keyboardKey] = elt.dataset.keyboardKey;
            keyboardHelp[elt.dataset.keyboardKey] =
              `<code>${elt.dataset.keyboardKey}</code> ${elt.dataset?.keyboardHelp ?? ''}`;
          });

          document.addEventListener('sl-show', (event) => {
            if (!event.target.querySelector('[data-island-action="help"]'))
              return;

            const helpKeyboard = event.target.querySelector('sl-tooltip > div');
            // Build the list from keyboardHelp values.
            helpKeyboard.innerHTML = `<ul class="db-keyboard-help">${Object.values(
              keyboardHelp,
            )
              .map((item) => `<li>${item}</li>`)
              .join('')}</ul>`;
          });

          document.addEventListener('keydown', (event) => {
            if (!keyboardMapping[event.key]) {
              return;
            }

            // Avoid action when on a textfield, textarea or CKEditor content.
            if (
              // @todo find a more generic way.
              event.target.tagName === 'SL-INPUT' ||
              event.target.tagName === 'SL-TEXTAREA' ||
              event.target.tagName === 'INPUT' ||
              event.target.tagName === 'TEXTAREA' ||
              event.target.classList.contains('ck-content')
            ) {
              return;
            }

            // Because buttons can be refreshed by HTMX we need to get the elt.
            const element = document.querySelector(
              `[data-keyboard-key="${event.key}"]`,
            );
            if (!element) return;

            // For some cases, the button is hidden instead of removed from dom.
            // Like state clear button.
            if (
              element.classList.contains('db-keyboard-mapping-clicked') ||
              element.classList.contains('hidden')
            ) {
              return;
            }

            element.click();

            // Visually give a sign of clicked button.
            element.focus();
            element.classList.add('db-keyboard-mapping-clicked');
            setTimeout(() => {
              element.blur();
              element.classList.remove('db-keyboard-mapping-clicked');
            }, 300);
          });
        },
      );
    },
  };
})(Drupal, once);
