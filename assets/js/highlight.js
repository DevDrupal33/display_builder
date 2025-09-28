/**
 * @param Drupal
 * @param once
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
   * Drupal behavior for display builder highlight.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for display builder highlight.
   *
   * @listens event:click
   */
  Drupal.behaviors.displayBuilderHighlight = {
    attach(context) {
      once('dbHighlight', '[data-set-highlight]', context).forEach((button) => {
        const builder = document.querySelector('.display-builder');
        button.addEventListener('click', () => {
          // @todo avoid using this kind of specific.
          const icon = button.querySelector('sl-icon');
          setHighlight(builder, icon, button);
        });
      });
    },
  };
})(Drupal, once, Drupal);
