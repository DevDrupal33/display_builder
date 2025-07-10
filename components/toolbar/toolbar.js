/**
 * @file
 * Attaches behaviors for Drupal's Display Builder Toolbar.
 */

((Drupal, once) => {
  /**
   * Enable Display builder Toolbar feature.
   *
   * @type {Drupal~behavior}
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
