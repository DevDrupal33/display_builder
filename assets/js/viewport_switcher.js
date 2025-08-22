/**
 * @file
 * Specific behaviors for the Display viewport switcher.
 */
/* eslint-disable no-use-before-define */
((Drupal, once) => {
  /**
   * Initialize the viewport switcher behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for viewportSwitcher.
   */
  Drupal.behaviors.viewportSwitcher = {
    attach(context) {
      once('dbViewportSwitcher', '.display-builder', context).forEach(
        (builder) => {
          setupViewPortSwitcher(builder);
        },
      );
    },
  };

  /**
   * Update main region width according to selected value.
   *
   * @param {HTMLElement} selector
   *   The viewport switcher select element.
   */
  function updateMainRegionWidth(selector) {
    // The ID of the Drupal breakpoint plugin.
    const breakpoint = selector.value;
    // The main region for View panels.
    const wrapper = selector
      .closest('.display-builder')
      .querySelector('.display-builder__main');
    const mapping = JSON.parse(selector.dataset.points);
    const widthWithUnit = breakpoint ? mapping[breakpoint] : '100%';
    // max-width to avoid overflow when the breakpoint is wider than the
    // available space.
    wrapper.style.maxWidth = widthWithUnit;
  }

  /**
   * Add event to each viewport selectors.
   *
   * @param {HTMLElement} builder
   *   The builder element to attach events to.
   *
   * @listens shoelace:sl-change
   */
  function setupViewPortSwitcher(builder) {
    const selectors = builder.querySelectorAll('.db-island-viewport sl-select');
    selectors.forEach((selector) => {
      selector.addEventListener('sl-change', () => {
        updateMainRegionWidth(selector);
      });
    });
  }
})(Drupal, once);
