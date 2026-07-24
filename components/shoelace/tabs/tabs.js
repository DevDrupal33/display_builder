/**
 * @param Drupal
 * @param once
 * @file
 * Specific behaviors for the display builder tabs component.
 */

((Drupal, once) => {
  /**
   * Synchronizes the visibility of tab panes based on active tab state.
   *
   * Also toggles any floating controls attached to this tab group's panes.
   * A floating control is rendered once and can ride several panes at once
   * (data-attached-to="<selector>,<selector>,..." @see
   * \Drupal\display_builder\ProfileViewBuilder::buildFloatingControlsRegion()),
   * so it stays visible while ANY of its attached panes is the active tab
   * rather than following a single one.
   *
   * @param {NodeList} tabs - Collection of tab elements
   */
  function syncPanes(tabs) {
    const tabArray = Array.from(tabs);
    const groupTargets = new Set();
    const activeTargets = new Set();

    tabArray.forEach((tab) => {
      const target = tab.getAttribute('data-target');
      const pane = document.querySelector(target);
      const isActive = tab.classList.contains('shoelace-tabs__tab--active');

      if (pane) {
        pane.classList.toggle('shoelace-tabs__tab--hidden', !isActive);
      }
      if (target) {
        groupTargets.add(target);
        if (isActive) {
          activeTargets.add(target);
        }
      }
    });

    // Only touch floating controls owned by this tab group (one of their
    // attached panes is one of this group's tabs), leaving another group's
    // controls to that group's own syncPanes() call.
    const root = tabArray[0]?.closest('.display-builder') ?? document;
    root.querySelectorAll('[data-attached-to]').forEach((el) => {
      const targets = el.getAttribute('data-attached-to').split(',');

      if (!targets.some((target) => groupTargets.has(target))) {
        return;
      }
      const anyActive = targets.some((target) => activeTargets.has(target));
      el.classList.toggle('shoelace-tabs__tab--hidden', !anyActive);
    });
  }

  /**
   * Switches the active tab and updates tab pane visibility.
   *
   * @param {string} builderId - The builder id.
   * @param {NodeList} tabs - Collection of tab elements
   * @param {HTMLElement} activeTab - The tab to make active
   * @param {string} tabId - The tab wrapper id.
   * @param {bool} saveState - Save the tab selected.
   */
  function switchTab(builderId, tabs, activeTab, tabId, saveState = true) {
    tabs.forEach((tab) => {
      tab.classList.remove('shoelace-tabs__tab--active');
      tab.removeAttribute('active');
      Drupal.displayBuilder.LocalStorageManager.remove(
        `tabActive.${tabId}`,
        builderId,
      );
    });
    activeTab.classList.add('shoelace-tabs__tab--active');
    activeTab.setAttribute('active', true);
    if (saveState) {
      Drupal.displayBuilder.LocalStorageManager.set(
        `tabActive.${tabId}`,
        activeTab.dataset.target,
        builderId,
      );
    }
    syncPanes(tabs);
  }

  /**
   * Adds click and keyboard event handlers for tab switching.
   *
   * @param {string} builderId - The builder id.
   * @param {HTMLElement} tabsComponent - The tabs container element
   *
   * @listens event:click
   * @listens event:keydown
   */
  function addSwitchingMechanism(builderId, tabsComponent) {
    const tabs = tabsComponent.querySelectorAll('.shoelace-tabs__tab');
    syncPanes(tabs);
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        switchTab(builderId, tabs, tab, tabsComponent.id);
      });
      tab.addEventListener('keydown', (event) => {
        if (event.keyCode === 13) {
          switchTab(builderId, tabs, tab, tabsComponent.id);
        }
      });
    });
  }

  /**
   * Checks if a tab pane is empty.
   *
   * @param {HTMLElement} pane - The tab pane element to check
   * @return {boolean} True if the pane is empty
   */
  function isPaneEmpty(pane) {
    return (
      pane.textContent.trim().length === 0 &&
      !pane.querySelector('input:not([type="hidden"])')
    );
  }

  /**
   * Hides empty tabs and activates the first visible tab.
   *
   * @param {string} builderId - The builder id.
   * @param {HTMLElement} tabsComponent - The tabs container element
   */
  function hideEmptyTabs(builderId, tabsComponent) {
    const tabs = tabsComponent.querySelectorAll('.shoelace-tabs__tab');
    Array.from(tabs).forEach((tab) => {
      const target = tab.getAttribute('data-target');
      const pane = document.querySelector(target);
      tab.classList.remove('shoelace-tabs__tab--hidden');
      if (isPaneEmpty(pane)) {
        tab.classList.add('shoelace-tabs__tab--hidden');
      }
    });
    const firstVisibleTab = tabsComponent.querySelector(
      '.shoelace-tabs__tab:not(.shoelace-tabs__tab--hidden)',
    );
    if (firstVisibleTab) {
      switchTab(builderId, tabs, firstVisibleTab, null, false);
    }
  }

  /**
   * Restore tabs state from local storage.
   *
   * @param {string} builderId - The builder id.
   * @param {NodeList} tabs - Collection of tab elements
   */
  function restoreTabsState(builderId, tabs) {
    const tabOpen = Drupal.displayBuilder.LocalStorageManager.get(
      `tabActive.${tabs.id}`,
      null,
      builderId,
    );
    if (!tabOpen) {
      return;
    }

    const tab = document.querySelector(
      `.shoelace-tabs__tab[data-target="${tabOpen}"]`,
    );
    if (!tab) {
      return;
    }

    const tabsList = tabs.querySelectorAll('.shoelace-tabs__tab');
    if (!tabsList) {
      return;
    }
    switchTab(builderId, tabsList, tab, null, false);
  }

  /**
   * Drupal behavior for display builder tabs.
   *
   * @todo move to specific DB JavaScript as it's not generic enough.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder tabs.
   */
  Drupal.behaviors.displayBuilderTabs = {
    attach(context) {
      once('shoelaceTabs', '.shoelace-tabs', context).forEach(
        (tabsComponent) => {
          const builder = tabsComponent.closest('.display-builder');
          if (!builder || !builder.id) return;
          const builderId = builder.id;
          addSwitchingMechanism(builderId, tabsComponent);
          // Restore tabs state from local storage
          restoreTabsState(builderId, tabsComponent);
        },
      );

      // Island instance form is dynamically loaded, once not persist on htmx
      // calls, need to act when it's loaded.
      // @todo pass to once
      if (
        context.classList &&
        context.classList.contains('db-island-contextual_form')
      ) {
        const tabsComponents = document.querySelectorAll(
          '.display-builder .shoelace-tabs--contextual',
        );
        Array.from(tabsComponents).forEach((tabsComponent) => {
          const builderId = tabsComponent.closest('.display-builder').id;
          hideEmptyTabs(builderId, tabsComponent);
          restoreTabsState(builderId, tabsComponent);
        });
      }
    },
  };
})(Drupal, once);
