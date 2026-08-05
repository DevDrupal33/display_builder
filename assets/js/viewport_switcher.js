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
      // Wire each switcher instance once. Keyed on the island wrapper rather
      // than on the whole .display-builder: the deferral system rebuilds a
      // hidden switcher by swapping the inner HTML of this same element
      // (@see components/display_builder/js/deferred_islands.js), so a switcher
      // added or rebuilt after the initial page attach must still get wired -
      // which a once() on the never-swapped .display-builder root would miss.
      // The click listener is delegated on this stable wrapper, so it survives
      // those inner-HTML swaps.
      once('dbViewportSwitcher', '.db-island-viewport', context).forEach(
        wireInstance,
      );

      // Restore visual state on every attach. A rebuilt switcher renders from
      // ViewportSwitcher::build(), which always emits the default (Responsive)
      // state, so it de-syncs from the width still applied to the preview pane.
      // The selected value lives on the builder root, which is never rebuilt, so
      // re-syncing here brings a freshly rebuilt instance back in line.
      // Idempotent for instances already showing the right state.
      const scope = context.querySelectorAll ? context : document;
      scope.querySelectorAll('.db-island-viewport').forEach((instance) => {
        const builder = instance.closest('.display-builder');
        if (builder) {
          applyInstanceState(instance, builder.dataset.dbViewport || '');
          const zoom = builder.dataset.dbZoom || '1';
          updatePreviewZoom(zoom, builder);
          applyZoomState(instance, zoom);
        }
      });
    },
  };

  /**
   * Update the preview width according to the selected value.
   *
   * @param {string} value
   *   The selected breakpoint ID, or '' for the Responsive default.
   * @param {string} points
   *   The JSON-encoded breakpoint-ID to width map.
   * @param {HTMLElement} builder
   *   The builder element.
   */
  function updateMainRegionWidth(value, points, builder) {
    // Only the Preview main-area pane simulates a viewport.
    const panes = builder.querySelectorAll(
      '.display-builder__main .db-island-preview',
    );
    if (!panes.length) return;
    const mapping = JSON.parse(points);
    const widthWithUnit = value ? mapping[value] : '';
    builder.classList.toggle('viewport-active', Boolean(value));
    // Set the width as a custom property on the pane, not inline on the iframe:
    // CSS applies it to the live iframe and its loading buffer alike, while the
    // pane's header bar keeps its full width. A width wider than the available
    // space still renders at its true size and scrolls (the pane's own overflow
    // handles it). Responsive (empty value) drops back to the base 100% via the
    // viewport-active class being removed.
    // @see components/display_builder/css/display_builder.css
    panes.forEach((pane) => {
      pane.style.setProperty('--db-preview-width', widthWithUnit);
    });
  }

  /**
   * Scale the Preview render according to the selected zoom level.
   *
   * Sets --db-preview-zoom on the Preview pane; CSS applies it as a transform
   * on the iframe (and its loading buffer). Independent of the chosen width,
   * so a wide device can be zoomed out to fit a narrow split.
   *
   * @param {string} value
   *   The zoom factor as a string, e.g. '0.5' for 50%. '1' is full size.
   * @param {HTMLElement} builder
   *   The builder element.
   */
  function updatePreviewZoom(value, builder) {
    const panes = builder.querySelectorAll(
      '.display-builder__main .db-island-preview',
    );
    panes.forEach((pane) => {
      pane.style.setProperty('--db-preview-zoom', value || '1');
    });
  }

  /**
   * Reflect the selected zoom level on a switcher's zoom select.
   *
   * @param {HTMLElement} instance - A single .db-island-viewport wrapper.
   * @param {string} value - The selected zoom factor, e.g. '0.5' or '1'.
   */
  function applyZoomState(instance, value) {
    instance.querySelectorAll('.switch-zoom-select').forEach((select) => {
      if (select.value !== value) {
        select.value = value;
      }
    });
  }

  /**
   * Reflect the selected value on a switcher's buttons (single active state).
   *
   * @param {HTMLElement} instance - A single .db-island-viewport wrapper.
   * @param {string} value - The selected breakpoint ID, or '' for Responsive.
   */
  function applyInstanceState(instance, value) {
    instance.querySelectorAll('.switch-viewport-btn').forEach((btn) => {
      const active = (btn.dataset.viewportValue || '') === value;
      btn.classList.toggle('active', active);
      // sl-button paints the primary variant filled; no variant is the neutral
      // default. Picking one button clears the primary from every other.
      if (active) {
        btn.setAttribute('variant', 'primary');
      } else {
        btn.removeAttribute('variant');
      }
    });
  }

  /**
   * Wire selection clicks on a single switcher instance.
   *
   * @param {HTMLElement} instance
   *   A single .db-island-viewport wrapper to attach events to.
   *
   * @listens click
   */
  function wireInstance(instance) {
    const builder = instance.closest('.display-builder');
    if (!builder) return;

    instance.addEventListener('click', (event) => {
      const btn = event.target.closest?.('.switch-viewport-btn');
      if (!btn) return;
      const control = btn.closest('.switch-viewport');
      if (!control?.dataset?.points) return;

      const value = btn.dataset.viewportValue || '';
      updateMainRegionWidth(value, control.dataset.points, builder);
      builder.dataset.dbViewport = value;
      applyInstanceState(instance, value);
    });

    // The zoom select fires sl-change (a Shoelace custom event) on selection.
    instance.addEventListener('sl-change', (event) => {
      const select = event.target.closest?.('.switch-zoom-select');
      if (!select) return;

      const value = select.value || '1';
      updatePreviewZoom(value, builder);
      builder.dataset.dbZoom = value;
    });
  }
})(Drupal, once);
