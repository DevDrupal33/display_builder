/**
 * @param Drupal
 * @param once
 * @file
 * Provides highlight behavior to the display builder.
 *
 * Four independent, non-exclusive checkboxes (drop zones/components/
 * blocks/extra spacing) instead of a single on/off toggle - each is
 * useful on its own - plus a "Select all"/"Unselect all" item that
 * checks/unchecks all four together and reflects whether they're all
 * already checked. The dropdown's own trigger button switches to the
 * "primary" variant whenever any one of the four is active, the same
 * way the old single on/off toggle used to.
 * @see \Drupal\display_builder\Plugin\display_builder\Island\HighlightToggle
 * @see components/dropzone/dropzone.css
 */

((Drupal, once) => {
  const HIGHLIGHT_KEYS = ['slot', 'component', 'block', 'space'];
  const ALL_KEY = 'all';

  /**
   * Whether a given highlight kind is currently active.
   *
   * @param {string} key - One of HIGHLIGHT_KEYS.
   *
   * @return {boolean} Whether that highlight is active.
   */
  function isActive(key) {
    return Boolean(
      Drupal.displayBuilder.LocalStorageManager.get(`highlight_${key}`),
    );
  }

  /**
   * Applies (or not) a single highlight kind, and persists it.
   *
   * @param {HTMLElement} builder - The builder element.
   * @param {string} key - One of HIGHLIGHT_KEYS.
   * @param {boolean} active - Whether it should be active.
   */
  function setHighlight(builder, key, active) {
    builder.classList.toggle(`display-builder--highlight-${key}`, active);
    if (active) {
      Drupal.displayBuilder.LocalStorageManager.set(`highlight_${key}`, true);
    } else {
      Drupal.displayBuilder.LocalStorageManager.remove(`highlight_${key}`);
    }
  }

  /**
   * Reflects whether any highlight kind is active on the dropdown's own
   * trigger button - the same "primary" vs. "default" variant switch the
   * old single on/off toggle used to do.
   *
   * @param {HTMLElement} dropdown - The [data-highlight-menu] element.
   */
  function updateTrigger(dropdown) {
    const button = dropdown.querySelector('sl-button[slot="trigger"]');
    if (!button) return;
    const active = HIGHLIGHT_KEYS.some((key) => isActive(key));
    button.setAttribute('variant', active ? 'primary' : 'default');
  }

  /**
   * Syncs the menu's own checkbox items, "Select all"'s own checked state
   * and label, and the trigger button, with whatever is currently stored.
   *
   * Selection is not preserved when changing page/swap - Shoelace renders
   * a fresh <sl-menu-item> with no `checked` each time, so this has to
   * happen again whenever the menu opens, not just after a click.
   *
   * @param {HTMLElement} dropdown - The [data-highlight-menu] element.
   */
  function syncMenu(dropdown) {
    const menu = dropdown.querySelector('sl-menu');
    if (!menu) return;

    let allActive = true;
    HIGHLIGHT_KEYS.forEach((key) => {
      const active = isActive(key);
      allActive = allActive && active;
      const item = menu.querySelector(`sl-menu-item[value="${key}"]`);
      if (item) {
        item.checked = active;
      }
    });

    const allItem = menu.querySelector(`sl-menu-item[value="${ALL_KEY}"]`);
    if (allItem) {
      allItem.checked = allActive;
      allItem.textContent = allActive
        ? Drupal.t('Unselect all')
        : Drupal.t('Select all');
    }

    updateTrigger(dropdown);
  }

  /**
   * Drupal behavior for display builder highlight.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for display builder highlight.
   *
   * @listens shoelace:sl-show
   * @listens shoelace:sl-select
   */
  Drupal.behaviors.displayBuilderHighlight = {
    attach(context) {
      once('dbHighlight', '.display-builder', context).forEach((builder) => {
        // Apply whatever's stored, independently of the menu ever being
        // opened - a fresh page load/HTMX swap always renders the builder
        // classless and the trigger button at its default variant.
        HIGHLIGHT_KEYS.forEach((key) =>
          setHighlight(builder, key, isActive(key)),
        );
        builder
          .querySelectorAll('[data-highlight-menu]')
          .forEach((dropdown) => updateTrigger(dropdown));

        builder.addEventListener('sl-show', (event) => {
          const dropdown = event.target.closest('[data-highlight-menu]');
          if (!dropdown) return;
          syncMenu(dropdown);
        });

        // Shoelace's <sl-menu> already toggles a type="checkbox" item's
        // own .checked before firing sl-select - just read it and sync.
        builder.addEventListener('sl-select', (event) => {
          const dropdown = event.target.closest('[data-highlight-menu]');
          if (!dropdown) return;
          const { item } = event.detail;

          if (item.value === ALL_KEY) {
            HIGHLIGHT_KEYS.forEach((key) =>
              setHighlight(builder, key, item.checked),
            );
          } else if (HIGHLIGHT_KEYS.includes(item.value)) {
            setHighlight(builder, item.value, item.checked);
          } else {
            return;
          }

          // "Select all"'s own checked state/label and the trigger
          // button both reflect whether every box ended up checked, not
          // just the one that was just clicked.
          syncMenu(dropdown);
        });
      });
    },
  };
})(Drupal, once);
