/**
 * @param Drupal
 * @param once
 * @file
 * Split view: pin the Preview pane beside the active editor pane.
 *
 * The toggle is a workspace-mode button in the toolbar's end region, beside the
 * other action buttons (@see
 * \Drupal\display_builder\ProfileViewBuilder::buildPreviewToggle()). Turning it
 * on flips the main region to a two-column flex row and copies the pinned pane
 * selector onto the main element; the tab group's syncPanes() then keeps that
 * pane visible whatever the active tab is (@see components/shoelace/tabs/tabs.js),
 * so co-location survives tab switches. State is remembered per builder.
 *
 * Full-width preview is not a separate mode, it is the end of the same axis:
 * the toggle owns whether Preview shows, the handle owns how much editor is
 * left. Dragging the handle past the left edge (or double-clicking it, or
 * pressing Home on it) collapses the editor column entirely, so Preview takes
 * the whole width and the main tab strip hides with it. The handle stays behind
 * as a rail: drag it back, double-click it, or press a View panel shortcut to
 * bring the editor back.
 */

((Drupal, once) => {
  Drupal.displayBuilder = Drupal.displayBuilder || {};

  /**
   * Storage key for the split state, scoped per builder.
   *
   * @type {string}
   */
  const STORAGE_KEY = 'splitView';

  /**
   * Storage key for the editor-column width ratio, scoped per builder.
   *
   * @type {string}
   */
  const RATIO_KEY = 'splitRatio';

  /**
   * Storage key for the collapsed (preview only) state, scoped per builder.
   *
   * @type {string}
   */
  const COLLAPSED_KEY = 'splitCollapsed';

  /**
   * Bounds of the editor-column ratio, in percent.
   *
   * @type {number}
   */
  const MIN_RATIO = 15;
  const MAX_RATIO = 85;

  /**
   * Ratio below which a drag collapses the editor instead of shrinking it.
   *
   * Sits under MIN_RATIO so the clamped range stays continuously reachable:
   * everything between the two is the dead zone that snaps to collapsed.
   *
   * @type {number}
   */
  const COLLAPSE_AT = 12;

  /**
   * How much one arrow key press moves the handle, in percent.
   *
   * @type {number}
   */
  const KEY_STEP = 5;

  /**
   * Clamp the editor-column ratio to a usable range (percent).
   *
   * @param {number} pct - The raw ratio in percent.
   * @return {number} The clamped ratio.
   */
  function clampRatio(pct) {
    return Math.min(MAX_RATIO, Math.max(MIN_RATIO, pct));
  }

  /**
   * Whether the editor column is currently collapsed (preview only).
   *
   * @param {HTMLElement} main - The .display-builder__main element.
   * @return {boolean} TRUE when only the Preview pane is showing.
   */
  function isCollapsed(main) {
    return !!main
      .closest('.display-builder')
      ?.classList.contains('display-builder--preview-only');
  }

  /**
   * Apply the editor-column width ratio as a custom property on the main region.
   *
   * The CSS reads --db-split-ratio as the editor pane's flex-basis; the Preview
   * pane fills the rest. Only meaningful in split mode, harmless otherwise.
   *
   * @param {HTMLElement} main - The .display-builder__main element.
   * @param {number} pct - The editor-column ratio, in percent.
   */
  function applyRatio(main, pct) {
    main.style.setProperty('--db-split-ratio', `${pct}%`);
    main
      .querySelector('.db-split-handle')
      ?.setAttribute('aria-valuenow', String(Math.round(pct)));
  }

  /**
   * Read the ratio currently applied to the main region.
   *
   * @param {HTMLElement} main - The .display-builder__main element.
   * @return {number} The editor-column ratio in percent, defaulting to even.
   */
  function currentRatio(main) {
    const pct = Number.parseFloat(
      main.style.getPropertyValue('--db-split-ratio'),
    );
    return Number.isNaN(pct) ? 50 : pct;
  }

  /**
   * Persist the editor-column ratio.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {number} pct - The editor-column ratio, in percent.
   */
  function saveRatio(builderId, pct) {
    Drupal.displayBuilder.LocalStorageManager.set(RATIO_KEY, pct);
  }

  /**
   * Collapse the editor column, or bring it back.
   *
   * The class goes on the builder root rather than the main region because the
   * main tab strip it also hides lives in the toolbar, outside main.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {HTMLElement} main - The .display-builder__main element.
   * @param {boolean} collapsed - Whether the editor column is collapsed.
   * @param {boolean} save - Whether to persist the new state.
   */
  function setCollapsed(builderId, main, collapsed, save = true) {
    const root = main.closest('.display-builder');
    // A drag reports the same state on every pointermove; only a real change is
    // worth re-syncing the panes for.
    const changed =
      !!root &&
      root.classList.contains('display-builder--preview-only') !== collapsed;
    root?.classList.toggle('display-builder--preview-only', collapsed);

    const handle = main.querySelector('.db-split-handle');
    if (handle) {
      handle.setAttribute(
        'aria-valuenow',
        collapsed ? '0' : String(Math.round(currentRatio(main))),
      );
      handle.setAttribute(
        'aria-label',
        collapsed
          ? Drupal.t('Restore the editor')
          : Drupal.t('Resize split view'),
      );
    }

    if (save) {
      Drupal.displayBuilder.LocalStorageManager.set(
        COLLAPSED_KEY,
        collapsed ? '1' : '',
      );
    }

    // The editor pane's floating controls (Highlight) are a separate
    // fixed box, not a child of the pane they act on, so hiding the column
    // leaves them sitting over the Preview. syncPanes() drops them once it sees
    // the collapsed root. @see components/shoelace/tabs/tabs.js
    if (changed) {
      Drupal.displayBuilder.syncMainTabs(root);
    }
  }

  /**
   * Turn a pointer position into an editor-column ratio.
   *
   * @param {HTMLElement} main - The .display-builder__main element.
   * @param {PointerEvent} event - The pointer event to read.
   * @return {number|null} The raw (unclamped) ratio, or NULL if main has no width.
   */
  function ratioFromEvent(main, event) {
    const rect = main.getBoundingClientRect();
    if (!rect.width) {
      return null;
    }
    return ((event.clientX - rect.left) / rect.width) * 100;
  }

  /**
   * Move the handle by one keyboard step, restoring the editor if it is away.
   *
   * Growing out of the collapsed rail lands on MIN_RATIO: the editor comes back
   * at its smallest rather than jumping to whatever ratio it had before.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {HTMLElement} main - The .display-builder__main element.
   * @param {number} delta - The step to apply, in percent.
   */
  function nudge(builderId, main, delta) {
    if (isCollapsed(main)) {
      if (delta < 0) {
        return;
      }
      setCollapsed(builderId, main, false);
      applyRatio(main, MIN_RATIO);
      saveRatio(builderId, MIN_RATIO);
      return;
    }

    const pct = clampRatio(currentRatio(main) + delta);
    applyRatio(main, pct);
    saveRatio(builderId, pct);
  }

  /**
   * Wire the handle's keyboard controls.
   *
   * A splitter that only answers to the mouse is unusable without one, so the
   * handle is focusable and carries the full separator contract: arrows resize,
   * Home collapses to preview only, End gives the editor its maximum, and
   * Enter or Space toggles between the two ends.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {HTMLElement} main - The .display-builder__main element.
   * @param {HTMLElement} handle - The drag handle.
   */
  function wireHandleKeys(builderId, main, handle) {
    handle.addEventListener('keydown', (event) => {
      if (!main.classList.contains('display-builder__main--split')) {
        return;
      }

      switch (event.key) {
        case 'ArrowLeft':
          nudge(builderId, main, -KEY_STEP);
          break;

        case 'ArrowRight':
          nudge(builderId, main, KEY_STEP);
          break;

        case 'Home':
          setCollapsed(builderId, main, true);
          break;

        case 'End':
          setCollapsed(builderId, main, false);
          applyRatio(main, MAX_RATIO);
          saveRatio(builderId, MAX_RATIO);
          break;

        case 'Enter':
        case ' ':
          setCollapsed(builderId, main, !isCollapsed(main));
          break;

        default:
          return;
      }

      event.preventDefault();
    });
  }

  /**
   * Insert the drag handle and wire it to resize the two split columns.
   *
   * The handle is a flex child ordered between the editor pane and the Preview
   * pane (@see components/display_builder/css/display_builder.css). Dragging it
   * rewrites the editor column ratio, or collapses the column when dragged past
   * COLLAPSE_AT; the final state is persisted per builder.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {HTMLElement} main - The .display-builder__main element.
   */
  function wireHandle(builderId, main) {
    const handle = document.createElement('div');
    handle.className = 'db-split-handle';
    handle.setAttribute('role', 'separator');
    handle.setAttribute('aria-orientation', 'vertical');
    handle.setAttribute('aria-label', Drupal.t('Resize split view'));
    handle.setAttribute('aria-valuemin', String(0));
    handle.setAttribute('aria-valuemax', String(MAX_RATIO));
    handle.setAttribute('tabindex', '0');
    main.appendChild(handle);

    const onMove = (event) => {
      const pct = ratioFromEvent(main, event);
      if (pct === null) {
        return;
      }
      // Preview the outcome live, without writing it to storage: the drag is
      // only committed on pointerup.
      setCollapsed(builderId, main, pct < COLLAPSE_AT, false);
      if (pct >= COLLAPSE_AT) {
        applyRatio(main, clampRatio(pct));
      }
    };

    const onUp = (event) => {
      handle.releasePointerCapture?.(event.pointerId);
      main.classList.remove('db-split-dragging');
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);

      const pct = ratioFromEvent(main, event);
      if (pct === null) {
        return;
      }
      const collapse = pct < COLLAPSE_AT;
      setCollapsed(builderId, main, collapse);
      if (!collapse) {
        saveRatio(builderId, clampRatio(pct));
      }
    };

    handle.addEventListener('pointerdown', (event) => {
      // Only resize in split mode.
      if (!main.classList.contains('display-builder__main--split')) return;
      event.preventDefault();
      handle.setPointerCapture?.(event.pointerId);
      main.classList.add('db-split-dragging');
      window.addEventListener('pointermove', onMove);
      window.addEventListener('pointerup', onUp);
    });

    handle.addEventListener('dblclick', () => {
      if (!main.classList.contains('display-builder__main--split')) return;
      setCollapsed(builderId, main, !isCollapsed(main));
    });

    wireHandleKeys(builderId, main, handle);
  }

  /**
   * Bring the editor back when a View panel is asked for while collapsed.
   *
   * Collapsing hides the main tab strip, but not the shortcuts that drive it:
   * keyboard.js answers those by clicking the matching tab (@see
   * components/display_builder/js/keyboard.js), which would otherwise switch a
   * pane nobody can see. Restoring the editor first makes the shortcut do what
   * it says.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {HTMLElement} root - The .display-builder element.
   * @param {HTMLElement} main - The .display-builder__main element.
   */
  function wireTabRestore(builderId, root, main) {
    root
      .querySelectorAll('.db-toolbar__middle .shoelace-tabs__tab')
      .forEach((tab) => {
        tab.addEventListener('click', () => {
          if (isCollapsed(main)) {
            setCollapsed(builderId, main, false);
          }
        });
      });
  }

  /**
   * Apply (or clear) the split state on the main region and its toggle.
   *
   * @param {string} builderId - The builder id, for state scoping.
   * @param {HTMLElement} main - The .display-builder__main element.
   * @param {HTMLElement} toggle - The split toggle button.
   * @param {boolean} active - Whether split view is on.
   * @param {boolean} save - Whether to persist the new state.
   */
  function setSplit(builderId, main, toggle, active, save = true) {
    main.classList.toggle('display-builder__main--split', active);

    if (active) {
      main.dataset.splitTarget = toggle.dataset.splitTarget;
      toggle.setAttribute('variant', 'primary');
    } else {
      // Leaving split always lands on a plain editor: a collapsed column kept
      // across the round trip would make the toggle restore preview only, which
      // is not what "show the preview beside the editor" promises.
      setCollapsed(builderId, main, false, save);
      delete main.dataset.splitTarget;
      toggle.removeAttribute('variant');
      // Hide the pane again here, before syncMainTabs() below: the pinned pane
      // is the Preview, which has no tab of its own, and syncPanes() only ever
      // toggles panes owned by a tab (@see components/shoelace/tabs/tabs.js).
      // Nothing else would put back the hidden class this mode removed, so the
      // Preview would stay in the main region - no longer a column, just
      // stacked under the editor, rendering the display a second time.
      main
        .querySelector(toggle.dataset.splitTarget)
        ?.classList.add('shoelace-tabs__tab--hidden');
    }
    toggle.setAttribute('aria-pressed', active ? 'true' : 'false');

    if (save) {
      Drupal.displayBuilder.LocalStorageManager.set(
        STORAGE_KEY,
        active ? '1' : '',
      );
    }

    // Reveal or hide the pinned pane now that the mode changed.
    Drupal.displayBuilder.syncMainTabs(main.closest('.display-builder'));
  }

  /**
   * Drupal behavior for the split-view toggle.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.displayBuilderSplit = {
    attach(context) {
      once('dbSplit', '[data-db-split-toggle]', context).forEach((toggle) => {
        const root = toggle.closest('.display-builder');
        if (!root || !root.id) {
          return;
        }
        const builderId = root.id;
        const main = root.querySelector('.display-builder__main');
        if (!main) {
          return;
        }

        wireHandle(builderId, main);
        wireTabRestore(builderId, root, main);

        const savedRatio = Drupal.displayBuilder.LocalStorageManager.get(
          RATIO_KEY,
          null,
        );
        if (savedRatio !== null) {
          applyRatio(main, clampRatio(Number(savedRatio)));
        }

        const saved = Drupal.displayBuilder.LocalStorageManager.get(
          STORAGE_KEY,
          '',
        );
        if (saved) {
          setSplit(builderId, main, toggle, true, false);
          if (
            Drupal.displayBuilder.LocalStorageManager.get(COLLAPSED_KEY, '')
          ) {
            setCollapsed(builderId, main, true, false);
          }
        }

        toggle.addEventListener('click', () => {
          const active = !main.classList.contains(
            'display-builder__main--split',
          );
          setSplit(builderId, main, toggle, active);
        });
      });
    },
  };
})(Drupal, once);
