/**
 * @file
 * Pins the history island's logs dropdown open.
 *
 * The dropdown lists the steps the undo and redo buttons beside it walk
 * through, so it has to survive using them. Two things would close a stock
 * sl-dropdown: Shoelace hides it on any pointer-down outside the dropdown,
 * the undo button included, and the island is rebuilt on every builder
 * event (@see IslandReloadEventsTrait), which renders a fresh, closed
 * dropdown. It closes on its own close button, on its trigger, and on
 * Escape only.
 * @see \Drupal\display_builder\Plugin\display_builder\Island\HistoryButtons
 */

((Drupal, once) => {
  const DROPDOWN = '[data-logs-dropdown]';

  /**
   * Wires one history island: open-state tracking and the close button.
   *
   * Delegated on the island wrapper, the one element a rebuild keeps - the
   * swap replaces its inner HTML, dropdown included.
   *
   * @param {HTMLElement} island - The .db-island-history wrapper.
   */
  function wireIsland(island) {
    const builder = island.closest('.display-builder');
    if (!builder) return;

    island.addEventListener('sl-show', (event) => {
      if (event.target.matches(DROPDOWN)) {
        builder.dataset.dbLogsOpen = 'true';
      }
    });

    // A dropdown torn out by a swap hides itself too, but detached, so that
    // one never bubbles up here. The guard keeps it that way regardless.
    island.addEventListener('sl-hide', (event) => {
      if (event.target.matches(DROPDOWN) && event.target.isConnected) {
        delete builder.dataset.dbLogsOpen;
      }
    });

    island.addEventListener('click', (event) => {
      const close = event.target.closest?.('[data-logs-close]');
      if (!close) return;
      const dropdown = close.closest(DROPDOWN);
      if (!dropdown) return;
      dropdown.hide();
      dropdown.querySelector('[slot="trigger"]')?.focus();
    });
  }

  /**
   * Keeps one dropdown from auto-closing, and reopens a rebuilt one.
   *
   * @param {HTMLElement} dropdown - The [data-logs-dropdown] element.
   */
  function pin(dropdown) {
    const builder = dropdown.closest('.display-builder');
    if (!builder) return;

    // Shoelace closes the dropdown on a pointer-down or a Tab outside its
    // containingElement, which defaults to the dropdown itself. The body
    // contains them all, so neither ever fires; Escape still closes.
    customElements.whenDefined('sl-dropdown').then(() => {
      dropdown.containingElement = document.body;
    });

    if (builder.dataset.dbLogsOpen) {
      dropdown.setAttribute('open', '');
    }
  }

  /**
   * Drupal behavior for the history island's logs dropdown.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the logs dropdown.
   */
  Drupal.behaviors.displayBuilderHistory = {
    attach(context) {
      once('dbHistoryLogs', '.db-island-history', context).forEach(wireIsland);

      // On every attach, not once: a rebuilt dropdown renders closed and the
      // open state lives on the builder root, which is never rebuilt.
      const scope = context.querySelectorAll ? context : document;
      scope.querySelectorAll(DROPDOWN).forEach(pin);
    },
  };
})(Drupal, once);
