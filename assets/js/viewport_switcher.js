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
      // Wire each switcher instance once. Keyed on the island element rather
      // than on the whole .display-builder: the deferral system rebuilds a
      // hidden switcher by swapping the inner HTML of this same element
      // (@see components/display_builder/js/deferred_islands.js), so a switcher
      // added or rebuilt after the initial page attach must still get wired -
      // which a once() on the never-swapped .display-builder root would miss.
      once('dbViewportSwitcher', '.db-island-viewport', context).forEach(
        (instance) => {
          wireInstance(instance);
        },
      );

      // Restore visual state on every attach. A rebuilt switcher renders from
      // ViewportSwitcher::build(), which always emits the default Fluid state,
      // so it de syncs from the width still applied to the panes and from the
      // other instance. The selected value lives on the builder root, which is
      // never rebuilt, so re-syncing here brings a freshly rebuilt instance
      // back in line. Idempotent for instances already showing the right state.
      const scope = context.querySelectorAll ? context : document;
      scope.querySelectorAll('.db-island-viewport').forEach((instance) => {
        const builder = instance.closest('.display-builder');
        if (builder) {
          applyInstanceState(instance, builder.dataset.dbViewport || '');
        }
      });
    },
  };

  /**
   * Update main region width according to selected value.
   *
   * @param {HTMLElement} breakpoint
   *   The ID of the Drupal breakpoint plugin.
   * @param {string} points
   *   The json encoded points list.
   * @param {HTMLElement} builder
   *   The builder element.
   */
  function updateMainRegionWidth(breakpoint, points, builder) {
    // Only the Builder and Preview main-area panes simulate a viewport.
    const panes = builder.querySelectorAll(
      '.display-builder__main .db-island-builder, .display-builder__main .db-island-preview',
    );
    if (!panes.length) return;
    const mapping = JSON.parse(points);
    const widthWithUnit = breakpoint ? mapping[breakpoint] : '';
    if (breakpoint) {
      builder.classList.add('viewport-active');
    } else {
      builder.classList.remove('viewport-active');
    }
    // An explicit width (not max-width) so a chosen viewport wider than the
    // available space still renders at its true size and the pane's scroll
    // container (.display-builder__main, overflow: auto) scrolls to it, instead
    // of clamping the preview down to whatever fits. max-width: none lifts any
    // natural cap so the width wins in both directions. Fluid clears both.
    panes.forEach((pane) => {
      pane.style.width = widthWithUnit;
      pane.style.maxWidth = breakpoint ? 'none' : '';
    });
  }

  /**
   * Reflect a selected value on a single switcher instance's own display.
   *
   * Split out from syncViewportInstances so a freshly rebuilt instance can be
   * restored on attach without re-broadcasting to the whole builder.
   *
   * @param {HTMLElement} instance - A single .db-island-viewport element.
   * @param {string} value - The selected breakpoint ID, or '' for Fluid.
   */
  function applyInstanceState(instance, value) {
    const menu = instance.querySelector('.viewport-menu');
    if (menu) {
      menu.querySelectorAll('sl-menu-item').forEach((item) => {
        item.classList.toggle('active', item.value === value);
      });
    }

    const btn = instance.querySelector('.switch-viewport-btn');
    if (btn) {
      if (value.length > 0) {
        btn.setAttribute('variant', 'primary');
        btn.setAttribute('outline', true);
      } else {
        btn.setAttribute('variant', 'default');
        btn.removeAttribute('outline');
      }
    }

    const selector = instance.querySelector('sl-select');
    // Guard against feedback loops: setting the same value again wouldn't
    // fire sl-change, but skip it explicitly for clarity.
    if (selector && selector.value !== value) {
      selector.value = value;
    }
  }

  /**
   * Sync every viewport switcher instance's own selected-state display.
   *
   * The switcher is a Floating control attached to both the Builder and
   * Preview panes (@see ViewportSwitcher.php), so there are two independent
   * widget instances - picking a viewport in one must be reflected in the
   * other's widget too, even though updateMainRegionWidth() already applies
   * the width to both panes regardless of which instance triggered it.
   *
   * The selected value is also stored on the builder root, the only element
   * the deferral system never rebuilds, so a switcher rebuilt while hidden can
   * restore itself from it on the next behavior attach.
   *
   * @param {HTMLElement} builder - The builder element.
   * @param {string} value - The selected breakpoint ID, or '' for Fluid.
   */
  function syncViewportInstances(builder, value) {
    builder.dataset.dbViewport = value;
    builder.querySelectorAll('.db-island-viewport').forEach((instance) => {
      applyInstanceState(instance, value);
    });
  }

  /**
   * Wire selection events on a single switcher instance.
   *
   * The listeners are delegated on the island element itself, not on its inner
   * menu or select, so they survive the inner-HTML swaps the deferral system
   * performs when it rebuilds this pane while it is off screen. Both Shoelace
   * events used here bubble to the island element.
   *
   * @param {HTMLElement} instance
   *   A single .db-island-viewport element to attach events to.
   *
   * @listens shoelace:sl-select
   * @listens shoelace:sl-change
   */
  function wireInstance(instance) {
    const builder = instance.closest('.display-builder');
    if (!builder) return;

    // Compact format: the menu inside the dropdown fires sl-select.
    instance.addEventListener('sl-select', (event) => {
      const menu = event.target.closest?.('.viewport-menu');
      if (!menu || !menu.dataset?.points) return;
      const { item } = event.detail;
      updateMainRegionWidth(item.value, menu.dataset.points, builder);
      syncViewportInstances(builder, item.value);
    });

    // Default format: the sl-select widget fires sl-change.
    instance.addEventListener('sl-change', (event) => {
      const selector = event.target.closest?.('sl-select');
      if (!selector || !selector.dataset?.points) return;
      updateMainRegionWidth(selector.value, selector.dataset.points, builder);
      syncViewportInstances(builder, selector.value);
    });
  }
})(Drupal, once);
