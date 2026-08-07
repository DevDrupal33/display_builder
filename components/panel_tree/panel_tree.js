/**
 * @param Drupal
 * @param once
 * @file
 * Specific behaviors for the Display builder tree panel.
 */

((Drupal, once) => {
  // Per-builder localStorage key remembering the single global tree view the
  // user last chose with the toolbar's "Collapse all" / "Expand all" buttons.
  // Stored explicitly as 'collapsed' or 'expanded' - not a collapsed-only flag -
  // so both choices survive a refresh symmetrically; an absent value means the
  // user picked neither and the server's own default expansion is kept. Only
  // this one global choice is persisted, never individual node toggles: those
  // would need server-side tracking, whereas reapplying one global state on
  // render is enough to keep the view the user chose after the tree island is
  // rebuilt (e.g. by a Canvas move). Keyed by builder id like the other
  // remembered UI state (@see assets/js/expand.js).
  const STATE_KEY = 'tree_collapse_state';

  /**
   * Toggles the highlight on the builder/scaffold element matching a tree row.
   *
   * @param {HTMLElement} node - The `.db-tree-node` (component/slot/block) element hovered.
   * @param {HTMLElement} builder - The `.display-builder` root element.
   * @param {boolean} add - Whether to add (hover in) or remove (hover out) the highlight.
   */
  function highlightInstance(node, builder, add) {
    const { nodeId, slotId } = node.dataset;

    // Light up exactly the one element the hovered row stands for in the other
    // view panels, never an ancestor:
    // - A slot row carries both its slot id and its parent's node id (@see
    //   BuilderPanel::buildSlotAttributes()), so match that slot's own dropzone
    //   alone - the parent's node id is shared by every sibling slot, hence
    //   pinning the slot id too.
    // - A component/block row matches only its own element. Its slots repeat its
    //   node id as their parent, so `:not([data-slot-id])` excludes them, and a
    //   child in a slot has its own node id so parents are never caught.
    const scope = '.db-island-view:not(.db-island-tree)';
    const selector = slotId
      ? `${scope} [data-slot-id="${slotId}"][data-node-id="${nodeId}"]`
      : `${scope} [data-node-id="${nodeId}"]:not([data-slot-id])`;

    builder.querySelectorAll(selector).forEach((elt) => {
      elt.classList.toggle('db-tree--selected', add);
    });
  }

  /**
   * Expands or collapses every collapsible node in a tree at once.
   *
   * @param {HTMLElement} tree - The `.db-tree` root element.
   * @param {boolean} expanded - Whether to expand (true) or collapse (false) all nodes.
   */
  function setAllExpanded(tree, expanded) {
    tree.querySelectorAll('.db-tree-node').forEach((node) => {
      const toggle = node.querySelector(
        ':scope > .db-tree-node__row > .db-tree-node__toggle',
      );
      if (!toggle) return;
      node.dataset.expanded = expanded ? 'true' : 'false';
      toggle.setAttribute('aria-expanded', String(expanded));
    });
  }

  /**
   * Flips a single node's own expanded/collapsed state.
   *
   * @param {HTMLElement} node - The `.db-tree-node` element to toggle.
   */
  function toggleNode(node) {
    const expanded = node.dataset.expanded !== 'false';
    node.dataset.expanded = expanded ? 'false' : 'true';
    const toggle = node.querySelector(
      ':scope > .db-tree-node__row > .db-tree-node__toggle',
    );
    if (toggle) toggle.setAttribute('aria-expanded', String(!expanded));
  }

  /**
   * Drupal behavior for tree panel.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder panel tree.
   */
  Drupal.behaviors.displayBuilderPanelTree = {
    attach(context) {
      once('dbTreeCollapseAll', '[data-tree-collapse-all]', context).forEach(
        (button) => {
          button.addEventListener('click', () => {
            const builder = button.closest('.display-builder');
            const tree = builder?.querySelector('.db-tree');
            if (!tree || !builder) return;
            setAllExpanded(tree, false);
            Drupal.displayBuilder.LocalStorageManager.set(
              STATE_KEY,
              'collapsed',
              builder.id,
            );
          });
        },
      );

      once('dbTreeExpandAll', '[data-tree-expand-all]', context).forEach(
        (button) => {
          button.addEventListener('click', () => {
            const builder = button.closest('.display-builder');
            const tree = builder?.querySelector('.db-tree');
            if (!tree || !builder) return;
            setAllExpanded(tree, true);
            Drupal.displayBuilder.LocalStorageManager.set(
              STATE_KEY,
              'expanded',
              builder.id,
            );
          });
        },
      );

      // Re-apply the remembered global collapse/expand state to freshly
      // rendered tree nodes. Keyed on each node, not on the whole tree: a Canvas
      // move re-renders only the moved node's subtree via a partial out-of-band
      // swap (@see BuilderPanel::replaceNode()) that leaves the `.db-tree` root -
      // and its once() marker - in place, so a tree-level hook would never re-run
      // and the swapped-in subtree (a fresh server render) would ignore the
      // stored state. once() skips nodes it has already handled, so on a full
      // render every node is set and on a partial swap only the new, unmarked
      // nodes are - keeping this cheap across repeated re-attaches. An absent
      // value leaves the server's own default expansion untouched.
      once('dbTreeCollapseState', '.db-tree-node', context).forEach((node) => {
        const builder = node.closest('.display-builder');
        if (!builder) return;
        const state = Drupal.displayBuilder.LocalStorageManager.get(
          STATE_KEY,
          null,
          builder.id,
        );
        if (state !== 'collapsed' && state !== 'expanded') return;
        const toggle = node.querySelector(
          ':scope > .db-tree-node__row > .db-tree-node__toggle',
        );
        if (!toggle) return;
        const expanded = state === 'expanded';
        node.dataset.expanded = expanded ? 'true' : 'false';
        toggle.setAttribute('aria-expanded', String(expanded));
      });

      // Bound directly on each toggle (rather than delegated on `.db-tree`)
      // so stopPropagation() runs before the click bubbles up to the
      // `.db-tree-node` itself, which carries its own hx-trigger="click"
      // (@see HtmxEvents::onInstanceClick()) opening the contextual form -
      // a toggle click must never also trigger that.
      once('dbTreeToggle', '.db-tree-node__toggle', context).forEach(
        (toggle) => {
          toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            toggleNode(toggle.closest('.db-tree-node'));
          });
        },
      );

      // A slot row isn't an instance - it has nothing of its own to open a
      // contextual form for (@see TreePanel::buildSingleComponent(), which
      // never calls onInstanceClick() on it) - so clicking it just
      // toggles collapse instead, like its own chevron button does.
      // stopPropagation() is required here too: unlike the chevron (a
      // separate element), this row sits *inside* its parent component's
      // own `.db-tree-node`, which does carry hx-trigger="click" - without
      // stopping the event it would bubble up and wrongly open the
      // parent's contextual form.
      once(
        'dbTreeSlotToggle',
        '.db-tree-node[data-menu-type="slot"] > .db-tree-node__row',
        context,
      ).forEach((row) => {
        row.addEventListener('click', (event) => {
          event.stopPropagation();
          toggleNode(row.closest('.db-tree-node'));
        });
      });

      once('dbTreeHover', '.db-tree-node__row', context).forEach((row) => {
        const node = row.closest('[data-node-id], [data-slot-id]');
        const builder = row.closest('.display-builder');
        if (!node || !builder) return;

        row.addEventListener('mouseenter', () => {
          highlightInstance(node, builder, true);
        });
        row.addEventListener('mouseleave', () => {
          highlightInstance(node, builder, false);
        });
      });
    },
  };
})(Drupal, once);
