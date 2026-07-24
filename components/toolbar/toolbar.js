/**
 * @file
 * Attaches behaviors for Drupal's Display Builder Toolbar.
 */
/* eslint no-use-before-define: 0 */

((Drupal, once) => {
  /**
   * Enable Display builder Toolbar switch on smaller width.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for display builder Toolbar.
   */
  Drupal.behaviors.displayBuilderToolbar = {
    attach(context) {
      once('dbToolbar', '.db-toolbar', context).forEach((toolbar) => {
        const { width } = toolbar.getBoundingClientRect();
        if (width < 650) {
          toolbar.querySelectorAll('.shoelace-button').forEach((btn) => {
            btn.setAttribute('size', 'small');
          });
        }
      });
    },
  };
})(Drupal, once);
