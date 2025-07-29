/**
 * @file
 * Provides fullscreen behavior to the display builder.
 */

((Drupal, once) => {
  /**
   * Set fullscreen for the display builder.
   *
   * @param {HTMLElement} builder - The builder element containing dropzone.
   * @param {HTMLElement|null} icon - The fullscreen icon.
   * @param {HTMLElement|null} button - The fullscreen button.
   *
   * @todo move to it's own component?
   */
  function setFullscreen(builder, icon, button) {
    if (builder.classList.contains('display-builder--fullscreen')) {
      document.documentElement.classList.remove(
        'display-builder-is-fullscreen',
      );
      builder.classList.remove('display-builder--fullscreen');
      if (icon) {
        icon.setAttribute('name', 'fullscreen');
      }
      if (button) {
        button.setAttribute('variant', 'default');
      }

      Drupal.displayBuilder.LocalStorageManager.remove(
        builder.id,
        'fullscreen',
      );

      // Clear any left margin set by sidebar width.
      const main = builder.querySelector('.display-builder__main');
      if (!main) return;
      main.style.marginLeft = '';
    } else {
      document.documentElement.classList.add('display-builder-is-fullscreen');
      builder.classList.add('display-builder--fullscreen');
      if (icon) {
        icon.setAttribute('name', 'fullscreen-exit');
      }
      if (button) {
        button.setAttribute('variant', 'primary');
      }
      // Remember fullscreen.
      Drupal.displayBuilder.LocalStorageManager.set(
        builder.id,
        'fullscreen',
        true,
      );

      // Hide sidebar if open to avoid margin issues.
      const firstDrawer = builder.querySelector('#db-first-drawer');
      if (firstDrawer && firstDrawer.open) {
        firstDrawer.hide();
      }
    }
  }

  /**
   * Set up sortable dropzone for the display builder.
   *
   * @param {HTMLElement} builder - The builder element containing dropzone.
   *
   * @todo move to it's own component?
   */
  function restoreFullscreen(builder) {
    if (
      Drupal.displayBuilder.LocalStorageManager.get(builder.id, 'fullscreen')
    ) {
      const button = builder.querySelector('[data-set-fullscreen]');
      // Specific shoelace event handler.
      // @todo avoid using this kind of specific.
      const icon = button.querySelector('sl-icon');
      setFullscreen(builder, icon, button);
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
  Drupal.behaviors.displayBuilderFullscreen = {
    attach(context) {
      once('dbFullscreen', '[data-set-fullscreen]', context).forEach(
        (button) => {
          const builder = button.closest('.display-builder');
          button.addEventListener('click', (event) => {
            // Click on button or icon is different.
            let icon = event.target;
            // Specific shoelace event handler.
            // @todo avoid using this kind of specific.
            if (event.target.tagName !== 'SL-ICON') {
              icon = event.target.querySelector('sl-icon');
            }

            setFullscreen(builder, icon, button);
          });
        },
      );

      once('dbFullscreenRestore', '.display-builder', context).forEach(
        (builder) => {
          restoreFullscreen(builder);
        },
      );
    },
  };
})(Drupal, once, Drupal);
