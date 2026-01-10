/**
 * @file
 * Build Container component initialization.
 *
 * CSS isolation is handled through CSS containment properties
 * without using Shadow DOM, ensuring compatibility with Playwright
 * and maintaining HTMX functionality.
 */

(function (Drupal) {
  'use strict';

  /**
   * Initialize build container behaviors.
   */
  Drupal.behaviors.dbBuildContainer = {
    attach: function (context) {
      const containers = context.querySelectorAll
        ? context.querySelectorAll('.db-build-container')
        : [];

      containers.forEach(container => {
        if (container.classList.contains('db-build-container--initialized')) {
          return;
        }
        container.classList.add('db-build-container--initialized');

        // Ensure HTMX is processed
        if (typeof htmx !== 'undefined') {
          htmx.process(container);
        }
      });
    }
  };

})(Drupal);
