/**
 * @file
 * Specific behaviors for keyboard mapping.
 *
 * If an element has a data-keyboard attribute with a key value, when the key is
 * pressed, the element is clicked.
 */

((Drupal) => {
  /**
   * Drupal behavior for keyboard mapping.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder functionality.
   * @listens event:keypress
   */
  Drupal.behaviors.displayBuilderKeyboard = {
    attach(context) {
      // Aggregate the keyboard values and create a mapping. Because the htmx
      // refresh we collect again on each refresh.
      const keyboardKeys = context.querySelectorAll('[data-keyboard]');
      if (!keyboardKeys) return;

      // Collect the keyboard mapping to associate with elements.
      const keyboardMapping = {};
      keyboardKeys.forEach((keyElement) => {
        keyboardMapping[keyElement.dataset.keyboard] = keyElement.id;
      });

      if (Object.keys(keyboardMapping).length === 0) {
        return;
      }

      document.addEventListener('keydown', (event) => {
        if (!keyboardMapping[event.key]) {
          return;
        }

        // Avoid action when on a textfield, textarea or CKEditor content.
        if (
          // @todo: find a more generic way.
          event.target.tagName === 'SL-INPUT' ||
          event.target.tagName === 'SL-TEXTAREA' ||
          event.target.tagName === 'INPUT' ||
          event.target.tagName === 'TEXTAREA' ||
          event.target.classList.contains('ck-content')
        ) {
          return;
        }

        const element = document.getElementById(keyboardMapping[event.key]);
        if (!element) {
          return;
        }
        // Avoid multiple click.
        if (element.classList.contains('db-keyboard-mapping-clicked')) {
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
  };
})(Drupal);
