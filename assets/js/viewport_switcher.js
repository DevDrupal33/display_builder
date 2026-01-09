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
   * Update main region width and preview iframe according to selected value.
   *
   * @param {HTMLElement} breakpoint
   *   The ID of the Drupal breakpoint plugin.
   * @param {string} points
   *   The json encoded points list.
   * @param {HTMLElement} builder
   *   The builder element.
   */
  function updateMainRegionWidth(breakpoint, points, builder) {
    // The main region for View panels.
    const wrapper = builder.querySelector('.display-builder__main');
    if (!wrapper) return;
    const mapping = JSON.parse(points);
    const widthWithUnit = breakpoint ? mapping[breakpoint] : '100%';
    if (breakpoint) {
      builder.classList.add('viewport-active');
    } else {
      builder.classList.remove('viewport-active');
    }
    // max-width to avoid overflow when the breakpoint is wider than the
    // available space.
    wrapper.style.maxWidth = widthWithUnit;

    // Also update preview iframe width for proper responsive preview.
    const previewIframe = builder.querySelector('.db-preview-iframe');
    if (previewIframe) {
      if (breakpoint && mapping[breakpoint] !== '100%') {
        previewIframe.style.width = widthWithUnit;
        previewIframe.style.maxWidth = widthWithUnit;
      } else {
        previewIframe.style.width = '100%';
        previewIframe.style.maxWidth = '100%';
      }
    }
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
    if (selectors.length > 0) {
      selectors.forEach((selector) => {
        if (!selector.dataset?.points) return;
        selector.addEventListener('sl-change', () => {
          updateMainRegionWidth(
            selector.value,
            selector.dataset.points,
            builder,
          );
        });
      });
    }

    const menu = builder.querySelector('.viewport-menu');
    if (!menu || !menu.dataset?.points) return;
    menu.addEventListener('sl-select', (event) => {
      const { item } = event.detail;
      updateMainRegionWidth(item.value, menu.dataset.points, builder);
      menu
        .querySelectorAll('sl-menu-item')
        .forEach((elt) => elt.classList.remove('active'));
      item.classList.add('active');

      const btn = builder.querySelector('.switch-viewport-btn');
      if (!btn) return;
      if (item.value.length > 0) {
        btn.setAttribute('variant', 'primary');
        btn.setAttribute('outline', true);
      } else {
        btn.setAttribute('variant', 'default');
      }
    });
  }
})(Drupal, once);
