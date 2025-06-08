/**
 * @file
 * Specific behaviors for the display builder.
 */

((Drupal, once) => {
  /**
   * Initialize display builder placeholder specifics.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder placeholder functionality.
   */
  Drupal.behaviors.displayBuilderPlaceholder = {
    attach(context) {
      once(
        'dbPlaceholderTippy',
        '.db-placeholder[data-preview-url]',
        context,
      ).forEach((element) => {
        Drupal.displayBuilder.initializeTippy(element);
      });
    },
  };
})(Drupal, once);
