/**
 * @param Drupal
 * @param once
 * @file
 * Provides expand (cover current viewport) behavior to the display builder.
 */

((Drupal, once) => {
  /**
   * Set expand mode for the display builder.
   *
   * @param {HTMLElement} builder - The builder element containing dropzone.
   * @param {HTMLElement|null} icon - The expand icon.
   * @param {HTMLElement|null} button - The expand button.
   */
  function setExpand(builder, icon, button) {
    if (builder.classList.contains('display-builder--expanded')) {
      document.documentElement.classList.remove('display-builder-is-expanded');
      builder.classList.remove('display-builder--expanded');
      if (icon) {
        icon.setAttribute('name', 'arrows-fullscreen');
      }
      if (button) {
        button.setAttribute('variant', 'default');
      }

      Drupal.displayBuilder.LocalStorageManager.remove('expand');
    } else {
      document.documentElement.classList.add('display-builder-is-expanded');
      builder.classList.add('display-builder--expanded');
      if (icon) {
        icon.setAttribute('name', 'fullscreen-exit');
      }
      if (button) {
        button.setAttribute('variant', 'primary');
      }
      // Remember expand.
      Drupal.displayBuilder.LocalStorageManager.set('expand', true);
    }
  }

  /**
   * Restore expand mode for the display builder.
   *
   * @param {HTMLElement} builder - The builder element containing dropzone.
   */
  function restoreExpand(builder) {
    if (Drupal.displayBuilder.LocalStorageManager.get('expand', null)) {
      const button = builder.querySelector('[data-set-expand]');
      const icon = button.querySelector('sl-icon');
      setExpand(builder, icon, button);
    }
  }

  /**
   * Drupal behavior for display builder expand.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for display builder expand.
   *
   * @listens event:click
   * @listens event:keydown
   */
  Drupal.behaviors.displayBuilderExpand = {
    attach(context) {
      once('dbExpand', '[data-set-expand]', context).forEach((button) => {
        const builder = button.closest('.display-builder');
        const icon = button.querySelector('sl-icon');

        button.addEventListener('click', () => {
          setExpand(builder, icon, button);
        });

        // Allow Escape to leave expand mode, mirroring native fullscreen.
        document.addEventListener('keydown', (event) => {
          if (
            event.key === 'Escape' &&
            builder.classList.contains('display-builder--expanded')
          ) {
            setExpand(builder, icon, button);
          }
        });
      });

      once('dbExpandRestore', '.display-builder', context).forEach(
        (builder) => {
          restoreExpand(builder);
        },
      );
    },
  };
})(Drupal, once);
