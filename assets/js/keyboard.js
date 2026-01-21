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
          const keyboardKeys = builder.querySelectorAll('[data-keyboard-key]');
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

          builder.addEventListener('sl-show', (event) => {
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
            // Ignore when any modifier other than Shift is active.
            const otherModifiersActive =
              event.metaKey ||
              event.ctrlKey ||
              event.altKey ||
              (event.getModifierState && event.getModifierState('AltGraph'));
            if (otherModifiersActive) {
              return;
            }

            // Avoid action when on a textfield, textarea or CKEditor content.
            const isInput = [
              'SL-INPUT',
              'INPUT',
              'SL-TEXTAREA',
              'TEXTAREA',
              'SL-SELECT',
              'SELECT',
            ].includes(document.activeElement.tagName);
            const isEditable = document.activeElement.isContentEditable;
            if (
              isInput ||
              isEditable ||
              (event.target.classList.contains('ck-content') &&
                event.key !== 'Escape')
            ) {
              return;
            }
            // We allow Shift modifier.
            const key = event.key;
            if (!keyboardMapping[key]) {
              return;
            }

            // Because buttons can be refreshed by HTMX we need to get the elt.
            const element = document.querySelector(
              `[data-keyboard-key="${key}"]`,
            );
            if (!element) return;

            // For some cases, the button is hidden instead of removed from dom.
            // Like state clear button.
            if (element.classList.contains('hidden')) {
              return;
            }

            // @todo add debounce
            element.click();
            element.focus();
          });
        },
      );
    },
  };
})(Drupal, once);
