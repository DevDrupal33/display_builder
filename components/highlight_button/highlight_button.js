/**
 * @file
 * Provides highlight behavior to the display builder.
 */

((Drupal, once) => {
  /**
   * Set highlight for the display builder.
   *
   * @param {HTMLElement} builder - The builder element containing dropzone.
   * @param {HTMLElement} icon - The fullscreen icon.
   * @param {HTMLElement} button - The fullscreen button.
   *
   * @todo move to it's own component?
   */
  function setHighlight(builder, icon, button) {
    if (builder.classList.contains('display-builder--highlight')) {
      builder.classList.remove('display-builder--highlight');
      if (icon) {
        icon.setAttribute('name', 'border-all');
      }
      if (button) {
        button.setAttribute('variant', 'default');
      }
    } else {
      builder.classList.add('display-builder--highlight');
      if (icon) {
        icon.setAttribute('name', 'border');
      }
      if (button) {
        button.setAttribute('variant', 'primary');
      }
    }
  }

  /**
   * Drupal behavior for display builder fullscreen .
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior.
   */
  Drupal.behaviors.displayBuilderHighlight = {
    attach(context) {
      once('dbHighlight', '[data-set-highlight]', context).forEach((button) => {
        const builder = button.closest('.display-builder');
        button.addEventListener('click', (event) => {
          // Click on button or icon is different.
          let icon = event.target;
          // Specific shoelace event handler.
          // @todo avoid using this kind of specific.
          if (event.target.tagName !== 'SL-ICON') {
            icon = event.target.querySelector('sl-icon');
          }

          setHighlight(builder, icon, button);
        });
      });
    },
  };
})(Drupal, once, Drupal);
