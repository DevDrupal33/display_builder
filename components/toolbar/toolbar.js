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
        stickyObserver(toolbar);
      });
    },
  };

  /**
   * Handle the toolbar is sticky at the top or not.
   * @param {HTMLElement} toolbar
   *   The sticky element.
   */
  const stickyObserver = (toolbar) => {
    const sentinel = document.querySelector('.db-toolbar-sentinel');

    // Use a simple top: 0, threshold: 0 setup for the sentinel.
    // You are just checking when the sentinel leaves/enters the viewport.
    const observerOptions = {
      root: null, // Default is the viewport
      threshold: 0,
    };

    const toolbarObserver = new IntersectionObserver(([entry]) => {
      // entry.isIntersecting:
      // - TRUE when sentinel is visible (Element is NOT stuck / Scrolling back up)
      // - FALSE when sentinel is out of view (Element IS stuck / Scrolling down)

      const stick = !entry.isIntersecting;

      // Toggle the class on the actual sticky toolbar
      toolbar.classList.toggle('db-toolbar-is-sticky', stick);
    }, observerOptions);

    toolbarObserver.observe(sentinel);
  };
})(Drupal, once);
