/**
 * @file
 * Specific behaviors for the shoelace drawer.
 */

((Drupal, once) => {
  function highlightInstance(event, builder) {
    const { instanceId } = event.detail.selection[0].dataset;
    const { slotId } = event.detail.selection[0].dataset;

    builder.querySelectorAll('.db-tree-selected').forEach((elt) => {
      elt.classList.remove('db-tree-selected');
    });

    builder
      .querySelectorAll(
        `.db-island-view:not(.db-island-tree) [data-instance-id="${instanceId}"]`,
      )
      .forEach((elt) => {
        if (elt.dataset?.instanceTitle) {
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
   *   Attaches the behaviors for display builder functionality.
   * @listens event:sl-selection-change
   */
  Drupal.behaviors.displayBuilderPanelTree = {
    attach(context) {
      if (
        context.classList &&
        context.classList.contains('db-island-builder')
      ) {
        const menu = document.querySelector('.db-tree');
        if (!menu) return;
        menu.addEventListener('sl-selection-change', (event) => {
          highlightInstance(event, context);
        });
      }

      once('dbTreeInit', '.db-tree', context).forEach((tree) => {
        tree.addEventListener('sl-selection-change', (event) => {
          highlightInstance(event, context);
        });
      });
    },
  };
})(Drupal, once);
