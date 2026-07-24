/**
 * @param Drupal
 * @param once
 * @file
 * Provides theme mode behavior to the display builder.
 */

((Drupal, once) => {
  /**
   * Initialize the display builder theme switch.
   *
   * This largely a copy of the Shoelace website theme switch. The switch is
   * set for all display builders, not only the current.
   *
   * @param {HTMLElement} builder - The builder element.
   *
   * @listens shoelace:sl-show
   * @listens shoelace:sl-hide
   * @listens event:change
   */
  function handleThemeSwitch(builder) {
    function getTheme() {
      return Drupal.displayBuilder.LocalStorageManager.get('theme') || 'auto';
    }

    let theme = getTheme();

    function isDark() {
      if (theme === 'auto') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
      }
      /* eslint no-return-assign: 0 */
      return theme === 'dark';
    }

    function updateSelection() {
      const menu = builder.querySelector('[data-theme-switch] sl-menu');
      if (!menu) return;
      [...menu.querySelectorAll('sl-menu-item')].map(
        (item) => (item.checked = item.getAttribute('value') === theme),
      );
      const icon = builder.querySelector('[data-theme-switch] sl-icon');
      if (!icon) return;
      icon.setAttribute('name', theme === 'dark' ? 'moon-fill' : 'sun');
    }

    function setTheme(newTheme) {
      theme = newTheme;
      Drupal.displayBuilder.LocalStorageManager.set('theme', theme);

      // Update the UI.
      updateSelection();

      // Toggle the dark mode class.
      builder.classList.toggle('sl-theme-dark', isDark());
    }

    // Selection is not preserved when changing page, so update when opening dropdown.
    builder.addEventListener('sl-show', (event) => {
      const themeSelector = event.target.closest('[data-theme-switch]');
      if (!themeSelector) return;
      updateSelection();
    });

    // Listen for selections.
    builder.addEventListener('sl-select', (event) => {
      const menu = event.target.closest('[data-theme-switch] sl-menu');
      if (!menu) return;
      setTheme(event.detail.item.value);
    });

    // Update the theme when the preference changes
    window
      .matchMedia('(prefers-color-scheme: dark)')
      .addEventListener('change', () => setTheme(theme));

    // Set the initial theme and sync the UI.
    setTheme(theme);
  }

  /**
   * Drupal behavior for display builder theme mode.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for display builder theme mode.
   */
  Drupal.behaviors.displayBuilderThemeMenu = {
    attach(context) {
      once('dbThemeInit', '.display-builder', context).forEach((builder) => {
        handleThemeSwitch(builder);
      });
    },
  };
})(Drupal, once);
