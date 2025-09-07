/**
 * @file
 * Specific behaviors for the shoelace drawer.
 */

((Drupal, once) => {
  function highlightInstance(event, builder) {
    const { nodeId } = event.detail.selection[0].dataset;
    const { slotId } = event.detail.selection[0].dataset;

    builder.querySelectorAll('.db-tree-selected').forEach((elt) => {
      elt.classList.remove('db-tree-selected');
    });

    builder
      .querySelectorAll(
        `.db-island-view:not(.db-island-tree) [data-node-id="${nodeId}"]`,
      )
      .forEach((elt) => {
        if (elt.dataset?.nodeTitle) {
          elt.classList.add('db-tree-selected');
        } else if (elt.dataset?.slotId === slotId) {
          elt.classList.add('db-tree-selected');
        }
      });
  }

  /**
   * Drupal behavior for tree panel.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder panel tree.
   *
   * @listens shoelace:sl-selection-change
   */
  Drupal.behaviors.displayBuilderPanelTree = {
    attach(context) {
      if (
        context.classList &&
        context.classList.contains('db-island-builder')
      ) {
        const menu = document.querySelector('.db-tree');
        if (!menu) return;
        // Specific shoelace event handler.
        // @todo avoid using this kind of specific.
        menu.addEventListener('sl-selection-change', (event) => {
          highlightInstance(event, context);
        });
      }

      once('dbTreeInit', '.db-tree', context).forEach((tree) => {
        // Specific shoelace event handler.
        // @todo avoid using this kind of specific.
        tree.addEventListener('sl-selection-change', (event) => {
          highlightInstance(event, context);
        });
      });
    },
  };
})(Drupal, once);
