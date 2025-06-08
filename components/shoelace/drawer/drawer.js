/**
 * @file
 * Specific behaviors for the shoelace drawer.
 */

((Drupal, once) => {
  /**
   * Drupal behavior for shoelace drawer.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for the drawer.
   * @listens event:click
   */
  Drupal.behaviors.displayBuilderDrawer = {
    attach(context) {
      once('dbDrawer', '[data-modal-target]', context).forEach((trigger) => {
        function handleDrawer(event) {
          const drawer = document.querySelector(
            `#${event.target.dataset.modalTarget}`,
          );
          if (!drawer || drawer.tagName !== 'SL-DRAWER') return;

          drawer.addEventListener('sl-after-show', () => {
            document.documentElement.classList.remove('sl-scroll-lock');
            document.documentElement.style.removeProperty(
              '--sl-scroll-lock-size',
            );
          });

          if (drawer.open) {
            drawer.hide();
            return;
          }
          if (!event.target.dataset.closeOnly) {
            drawer.show();
          }
        }

        trigger.addEventListener('click', handleDrawer);
      });
    },
  };
})(Drupal, once);
