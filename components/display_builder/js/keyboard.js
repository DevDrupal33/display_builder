/* eslint max-nested-callbacks: 0 */
/**
 * @param Drupal
 * @param once
 * @file
 * Specific behaviors for keyboard mapping.
 *
 * If an element has a data-keyboard-key attribute with a key value, when the
 * matching key combination is pressed, the element is clicked.
 *
 * A key value is a canonical "combo" string: zero or more modifier tokens
 * followed by the key, joined with "+", in a fixed order - e.g. "b",
 * "shift+p", "mod+z", "mod+shift+z", "Delete". The "mod" token is the
 * per-platform primary accelerator: Cmd on macOS, Ctrl everywhere else, so a
 * shortcut is declared once and works on both an Apple and a PC keyboard.
 * A single element may advertise several combos, space-separated
 * (e.g. "mod+z u"); the first is the one shown in the help dialog.
 */

((Drupal, once) => {
  // macOS uses Cmd (metaKey) as the primary accelerator; every other platform
  // uses Ctrl (ctrlKey). navigator.platform is deprecated but still the most
  // reliable synchronous signal, with userAgentData preferred where present.
  const isMac = /mac|iphone|ipad|ipod/i.test(
    navigator.userAgentData?.platform || navigator.platform || '',
  );
  const MOD_LABEL = isMac ? '⌘' : 'Ctrl';

  /**
   * Builds the canonical combo string for a keydown event.
   *
   * The order is fixed (mod, alt, shift, key) and single characters are
   * lowercased, so a shortcut is matched the same way it is declared
   * regardless of Shift or Caps Lock. Every active modifier is included, so an
   * undeclared combination (e.g. Alt+b) can never collide with a plain
   * shortcut ("b").
   *
   * @param {KeyboardEvent} event
   *   The keydown event.
   *
   * @return {string}
   *   The combo, e.g. "mod+shift+z".
   */
  const comboFromEvent = (event) => {
    const parts = [];
    if (isMac ? event.metaKey : event.ctrlKey) parts.push('mod');
    if (event.altKey) parts.push('alt');
    if (event.shiftKey) parts.push('shift');
    const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    parts.push(key);
    return parts.join('+');
  };

  /**
   * Renders a combo for the help dialog, e.g. "mod+z" as "⌘ Z" / "Ctrl + Z".
   *
   * @param {string} combo
   *   A canonical combo string.
   *
   * @return {string}
   *   HTML with each token wrapped in a <code> element.
   */
  const prettyCombo = (combo) =>
    combo
      .split('+')
      .map((part) => {
        if (part === 'mod') return MOD_LABEL;
        if (part === 'shift') return isMac ? '⇧' : 'Shift';
        if (part === 'alt') return isMac ? '⌥' : 'Alt';
        return part.length === 1 ? part.toUpperCase() : part;
      })
      .map((label) => `<code>${label}</code>`)
      .join('');

  /**
   * Drupal behavior for keyboard mapping.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder functionality.
   *
   * @listens event:keydown
   */
  Drupal.behaviors.displayBuilderKeyboard = {
    attach(context) {
      once('dbKeyboardGlobal', '.display-builder', context).forEach(
        (builder) => {
          const keyboardKeys = builder.querySelectorAll('[data-keyboard-key]');

          const keyboardMapping = {};
          const keyboardHelp = {};
          keyboardKeys.forEach((elt) => {
            if (!elt.dataset?.keyboardKey) {
              return;
            }
            // One element may declare several combos, space-separated. Every
            // combo is a matcher; only the first is shown in the help dialog.
            const combos = elt.dataset.keyboardKey.split(/\s+/).filter(Boolean);
            combos.forEach((combo) => {
              keyboardMapping[combo] = combo;
            });
            keyboardHelp[combos[0]] =
              `${prettyCombo(combos[0])} ${elt.dataset?.keyboardHelp ?? ''}`;
          });

          builder.addEventListener('sl-show', (event) => {
            if (!event.target.querySelector('[data-island-action="help"]'))
              return;

            const helpKeyboard = event.target.querySelector('sl-tooltip > div');
            // Build the list from keyboardHelp values.
            helpKeyboard.innerHTML = `<ul class="db-keyboard-help">${Object.values(
              keyboardHelp,
            )
              .map((item) => `<li>${item}</li>`)
              .join('')}</ul>`;
          });

          document.addEventListener('keydown', (event) => {
            // The non-primary platform accelerator is never one of our
            // modifiers (Ctrl on macOS, the Windows/Command key elsewhere), so
            // e.g. Ctrl+Z on a Mac must not match "mod+z". AltGraph is a Ctrl+
            // Alt combination on some layouts and is never a shortcut here.
            const wrongMod = isMac ? event.ctrlKey : event.metaKey;
            if (
              wrongMod ||
              (event.getModifierState && event.getModifierState('AltGraph'))
            ) {
              return;
            }

            // Avoid action when on a textfield, textarea or CKEditor content.
            const isInput = [
              'SL-INPUT',
              'INPUT',
              'SL-TEXTAREA',
              'TEXTAREA',
              'SL-SELECT',
              'SELECT',
            ].includes(document.activeElement.tagName);
            const isEditable = document.activeElement.isContentEditable;
            if (
              isInput ||
              isEditable ||
              (event.target.classList.contains('ck-content') &&
                event.key !== 'Escape')
            ) {
              return;
            }

            const combo = comboFromEvent(event);
            if (!keyboardMapping[combo]) {
              return;
            }

            // Because buttons can be refreshed by HTMX we need to get the elt.
            // A single element may advertise several combos (space-separated),
            // hence the ~= token match rather than a plain equality.
            const element = document.querySelector(
              `[data-keyboard-key~="${combo}"]`,
            );
            if (!element) return;

            // For some cases, the button is hidden instead of removed from dom.
            // Like state clear button.
            if (element.classList.contains('hidden')) {
              return;
            }

            // Contextual menu items (e.g. Remove, bound to Delete) live in the
            // single shared .db-menu, which normally learns which node it acts
            // on from the right-click that opened it
            // (@see components/contextual_menu/contextual_menu.js's
            // updateMenuItems()). Reached by keyboard the menu was never
            // opened, so resolve the target here instead: the selected node is
            // the one wearing .db-node-contextual-open - the same class that
            // draws its outline, added when a node is clicked and removed when
            // its sidebar closes (@see js/sidebar.js). Relying on that class
            // rather than a separately tracked id keeps the key acting on
            // exactly what the user can see is selected, and makes "nothing
            // selected" a no-op instead of a delete of some stale node.
            // Only a *visible* candidate counts, hence the filter rather than
            // a plain querySelector. Clicking a node marks every element
            // carrying its id across all panels (@see js/sidebar.js's
            // toggleOpenClassOnClick), including the panels currently hidden
            // behind another tab - and a hidden panel is only flagged stale on
            // change, re-rendered when it is next revealed
            // (@see js/deferred_islands.js), so its marked elements outlive the
            // node itself once that node is deleted. querySelector returns the
            // first match in *document order*, which is one of those hidden
            // panels, so pressing Delete repeatedly would sooner or later
            // resolve to the ghost of an already-deleted node, silently doing
            // nothing from then on however many times it was pressed
            // (reproduced: reliably dead by the third delete).
            if (element.dataset.contextualMenu) {
              const selected = [
                ...builder.querySelectorAll(
                  '.db-node-contextual-open[data-node-id]',
                ),
              ].find((candidate) => candidate.checkVisibility());
              if (!selected) return;
              const nodeId = selected.dataset.nodeId;

              // `selected` may be any element tagged with this node id - the
              // class is stamped on every rendered copy across panels, and even
              // on a slot wrapper the node *owns* (which carries the node id as
              // its parent). For slot placement we need the node's own wrapper
              // (never itself a slot) and a *visible* one, so the resolved slot
              // is the live one and not a stale ghost in a deferred panel.
              // @see components/display_builder/js/sidebar.js
              // @see components/display_builder/js/deferred_islands.js
              const nodeEl =
                [
                  ...builder.querySelectorAll(
                    `[data-node-id="${nodeId}"]:not([data-slot-id])`,
                  ),
                ].find((el) => el.checkVisibility()) ?? selected;

              // Stamp on the menu item the same node/slot context a right-click
              // would have resolved through updateMenuItems(), derived from the
              // selected node instead. The enclosing slot wrapper carries the
              // owning node id and slot machine name; the node carries its own
              // position within that slot. Position is stored 1-based (index +
              // 1) so the duplicate handler's "- 1" lands the copy back at the
              // source index, matching the right-click path; paste (no "- 1")
              // then lands next to it.
              // @see components/contextual_menu/contextual_menu.js
              // @see \Drupal\display_builder\Plugin\display_builder\Island\BuilderPanel::buildSlotAttributes()
              const slot = nodeEl.closest('[data-slot-id]');
              const position =
                parseInt(nodeEl.dataset.slotPosition ?? '0', 10) + 1;
              element.setAttribute('data-node-id', nodeId);
              element.setAttribute(
                'data-node-title',
                nodeEl.dataset.nodeTitle ?? '',
              );
              element.setAttribute(
                'data-slot-node-id',
                slot?.dataset.nodeId ?? '__root__',
              );
              element.setAttribute(
                'data-slot-id',
                slot?.dataset.slotId ?? '__none__',
              );
              element.setAttribute('data-slot-position', `${position}`);

              const menu = element.closest('.db-menu');
              const action = element.getAttribute('value');

              // Copy is a client-side clipboard write, not an HTMX request, and
              // its sl-select handler is only wired once a right-click has
              // opened the menu, so a keyboard copy writes the clipboard here.
              // @see components/contextual_menu/contextual_menu.js's setupMenuSelectHandler()
              if (action === 'copy') {
                event.preventDefault();
                Drupal.displayBuilder.LocalStorageManager.set(
                  'copy',
                  { id: nodeId, title: nodeEl.dataset.nodeTitle ?? '' },
                  menu?.dataset.dbId,
                );
                return;
              }

              // Paste needs the id of the previously copied node; bail when the
              // clipboard is empty. A stale "disabled" left by an earlier
              // right-click must be cleared or the click won't fire the request.
              if (action === 'paste') {
                const copied = Drupal.displayBuilder.LocalStorageManager.get(
                  'copy',
                  { id: null },
                  menu?.dataset.dbId,
                );
                if (!copied?.id) return;
                element.setAttribute('data-copy-instance-id', copied.id);
                element.removeAttribute('disabled');
              }
            }

            // A matched shortcut owns the keystroke: stop the browser acting on
            // it too (e.g. Cmd/Ctrl+Z, or a plain letter reaching a listener
            // elsewhere).
            event.preventDefault();

            // @todo add debounce
            element.click();
            element.focus();
          });
        },
      );
    },
  };
})(Drupal, once);
