/**
 * @param Drupal
 * @param once
 * @param Sortable
 * @file
 * Attaches behaviors for Drupal's Display Builder Dropzone.
 *
 * cspell:ignore dbDropzoneRescan dbDropzoneCleanup
 */

((Drupal, once, Sortable) => {
  // Custom drag autoscroll.
  //
  // SortableJS' built-in scroll (scroll / scrollSensitivity / scrollSpeed) does
  // not reliably scroll in this builder, because the element that actually
  // scrolls varies by route: the .display-builder__main pane (page-layout, its
  // parent has a fixed height), the whole document (embedded route, the pane
  // has a natural height and never scrolls itself), or .display-builder--expanded
  // (fullscreen overlay). The plugin detects one scrollable ancestor up front
  // and derives its hot zones from that container's rect - with native HTML5
  // drag it also gets no continuous pointer position near a viewport-clipped
  // edge, so the bottom hot zone is usually off screen and never reached.
  //
  // Instead we watch the drag pointer and, each frame, scroll whichever
  // container is under it toward the edge it is approaching. A single rAF loop
  // runs for the whole drag (not just on pointer move), so holding still inside
  // a hot zone keeps scrolling.
  //
  // Distance from a container edge, in px, where autoscroll begins.
  const AUTOSCROLL_EDGE = 60;
  // Maximum scroll step, in px per frame, reached at the very edge.
  const AUTOSCROLL_MAX_SPEED = 18;

  let autoScrollActive = false;
  let autoScrollFrame = null;
  let autoScrollY = 0;

  /**
   * Records the current drag pointer position.
   *
   * @param {DragEvent} event - The dragover event.
   */
  function trackDragPointer(event) {
    autoScrollY = event.clientY;
  }

  /**
   * Finds the nearest vertically scrollable container under a viewport point.
   *
   * Falls back to the document's scrolling element (window scroll) when no
   * scrollable ancestor is found - this is what makes the embedded route work.
   *
   * @param {number} y - Viewport Y coordinate of the pointer.
   *
   * @return {HTMLElement} The scroll container to act on.
   */
  function findScrollContainer(y) {
    // A tiny inset off the left keeps elementFromPoint off scrollbars/edges.
    let node = document.elementFromPoint(8, y);
    while (
      node &&
      node !== document.body &&
      node !== document.documentElement
    ) {
      const style = getComputedStyle(node);
      if (
        /(auto|scroll|overlay)/.test(style.overflowY) &&
        node.scrollHeight > node.clientHeight
      ) {
        return node;
      }
      node = node.parentElement;
    }
    return document.scrollingElement || document.documentElement;
  }

  /**
   * Scrolls the container under the pointer while the pointer sits near an edge.
   */
  function autoScrollTick() {
    if (!autoScrollActive) return;

    const container = findScrollContainer(autoScrollY);
    const isRoot =
      container === document.scrollingElement ||
      container === document.documentElement ||
      container === document.body;
    // The document's scrolling element reports the full page height in its
    // bounding rect, not the visible area - use the viewport for window scroll.
    const top = isRoot ? 0 : container.getBoundingClientRect().top;
    const bottom = isRoot
      ? window.innerHeight
      : container.getBoundingClientRect().bottom;

    let delta = 0;
    const topDistance = autoScrollY - top;
    const bottomDistance = bottom - autoScrollY;
    if (topDistance < AUTOSCROLL_EDGE) {
      delta =
        -AUTOSCROLL_MAX_SPEED *
        (1 - Math.max(topDistance, 0) / AUTOSCROLL_EDGE);
    } else if (bottomDistance < AUTOSCROLL_EDGE) {
      delta =
        AUTOSCROLL_MAX_SPEED *
        (1 - Math.max(bottomDistance, 0) / AUTOSCROLL_EDGE);
    }
    if (delta) {
      container.scrollBy(0, delta);
    }

    autoScrollFrame = requestAnimationFrame(autoScrollTick);
  }

  /**
   * Starts the autoscroll loop for the duration of a drag.
   */
  function startAutoScroll() {
    if (autoScrollActive) return;
    autoScrollActive = true;
    // Capture phase so we still see the position even if a handler stops
    // propagation, and passive since we never call preventDefault here.
    document.addEventListener('dragover', trackDragPointer, {
      capture: true,
      passive: true,
    });
    autoScrollFrame = requestAnimationFrame(autoScrollTick);
  }

  /**
   * Stops the autoscroll loop when the drag ends.
   */
  function stopAutoScroll() {
    autoScrollActive = false;
    document.removeEventListener('dragover', trackDragPointer, {
      capture: true,
    });
    if (autoScrollFrame !== null) {
      cancelAnimationFrame(autoScrollFrame);
      autoScrollFrame = null;
    }
  }

  /**
   * Set up sortable dropzone for the display builder.
   *
   * @param {HTMLElement} dropzoneRoot - The element containing dropzone.
   */
  function setDropzone(dropzoneRoot) {
    const builderId = dropzoneRoot.dataset.dbId;
    // The Navigator (tree) panel reuses the dropzone component, so its rows are
    // draggable like the Canvas - but it must only allow moves *within itself*.
    // Dragging a Canvas/Scaffold node or a library item into it produced a
    // broken (caught) request. Give the tree its own group name so it is
    // distinguishable: its root carries `.db-tree`
    // (@see components/panel_tree/panel_tree.twig) and every nested dropzone is
    // initialized from that same root, so one check on the root covers the
    // whole tree. The name alone is not enough (SortableJS `put: true` accepts
    // from any group) - the same-group `put` below is what actually isolates it.
    const groupName = dropzoneRoot.classList.contains('db-tree')
      ? `${builderId}__tree`
      : builderId;
    const sortableSettings = {
      // Add custom classes to style precisely.
      // @see components/dropzone/dropzone.css
      ghostClass: 'db-dropzone--ghost',
      chosenClass: 'db-dropzone--chosen',
      dragClass: 'db-dropzone--drag',
      // https://sortablejs.github.io/Sortable/#thresholds
      animation: 100,
      // direction: 'vertical',
      swapThreshold: 0.65,
      // fallbackOnBody: true,
      // Let a drop land in the "other half" of the swap zone too - makes
      // narrow/adjacent dropzones (e.g. columns of a nested grid) easier to
      // drop into, not just before/after them.
      invertSwap: true,
      emptyInsertThreshold: 5,
      group: {
        name: groupName,
        pull: true,
        // Accept only from a list sharing this one's group name. Canvas,
        // Scaffold and the library all share the builder id, so their drops
        // are unaffected; the Navigator's own `<id>__tree` group only
        // exchanges rows with itself - nothing drops in from another panel,
        // and its rows can't be dragged out either. `put: true` would accept
        // from any group regardless of name, which is exactly what let a
        // Canvas node drop into the tree before.
        put: (to, from) => to.options.group.name === from.options.group.name,
      },
      // Built-in scroll is off: it can't handle this builder's three different
      // scroll containers. We drive autoscroll ourselves from the drag pointer
      // instead - @see startAutoScroll above.
      scroll: false,
      onStart() {
        dropzoneRoot
          .closest(`[id="${builderId}"]`)
          .classList.add('display-builder--on-move');
        startAutoScroll();
      },
      onEnd() {
        dropzoneRoot
          .closest(`[id="${builderId}"]`)
          .classList.remove('display-builder--on-move');
        stopAutoScroll();
      },
    };

    // Init the dropzone root itself.
    if (!Sortable.get(dropzoneRoot)) {
      Sortable.create(dropzoneRoot, sortableSettings);
    }

    const dropzoneList = dropzoneRoot.getElementsByClassName('db-dropzone');
    const dropzoneLength = dropzoneList.length;
    if (!dropzoneLength || dropzoneLength === 0) return;

    for (let i = 0; i < dropzoneLength; i++) {
      const dropzone = dropzoneList[i];
      if (!Sortable.get(dropzone)) {
        Sortable.create(dropzone, sortableSettings);
      }
    }
  }

  /**
   * Sets up a root dropzone and arranges for its eventual replacement to
   * be picked up too.
   *
   * A drop can cause the whole panel to be re-rendered and swapped in via
   * an out-of-band fragment targeting the panel's own container, which
   * replaces this root dropzone with a brand new node living elsewhere in
   * that same markup. htmx dispatches htmx:oobAfterSwap, for every oob
   * fragment in a response, on the element that triggered the request -
   * here, this dropzone - including the fragment that replaces it. A
   * listener bound directly to this node still receives that event even
   * after the node has been detached (unlike a window-level listener,
   * which needs an unbroken bubble path to the document); re-scanning for
   * not-yet-seen root dropzones whenever it fires picks up the replacement
   * node and, by initializing it the same way, keeps this chain going for
   * however many drops follow.
   *
   * @param {HTMLElement} dropzoneRoot - The element containing dropzone.
   */
  function initRootDropzone(dropzoneRoot) {
    setDropzone(dropzoneRoot);
    dropzoneRoot.addEventListener('htmx:oobAfterSwap', () => {
      once('dbDropzone', '.db-dropzone--root', document).forEach(
        initRootDropzone,
      );
    });
  }

  /**
   * Enable Display builder Dropzone feature.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for Display builder dropzone.
   */
  Drupal.behaviors.displayBuilderDropzone = {
    attach(context) {
      once('dbDropzone', '.db-dropzone--root', context).forEach(
        initRootDropzone,
      );

      // A swap can rebuild a panel without the re-init reaching the code
      // above. The htmx:oobAfterSwap chain and the module's own
      // htmx:drupal:load re-attach (@see display_builder.js) both key off
      // the request's *source* element, which core also uses as the target
      // it fires those events on. When the source is removed by its own
      // response - e.g. the Restore / Publish state buttons, which disappear
      // once the state they toggle is reached - those events fire on a
      // detached node and never bubble to any live listener, so the rebuilt
      // panels keep no Sortable and every drag dies until a full page reload.
      //
      // htmx:load is different: htmx fires it on every element it inserts,
      // always the connected new node, regardless of the request source's
      // fate. Re-scan the document for not-yet-initialized root dropzones on
      // every load; once('dbDropzone') skips roots already handled (here, in
      // attach(), and in the oobAfterSwap chain all share the same marker),
      // so the repeated scans are cheap and never double-initialize.
      once('dbDropzoneRescan', 'body', context).forEach((body) => {
        body.addEventListener('htmx:load', (event) => {
          // Full panel rebuilds insert a brand-new root dropzone: initialize
          // each once (setDropzone plus the oob-swap re-init chain).
          once('dbDropzone', '.db-dropzone--root', document).forEach(
            initRootDropzone,
          );
          // Partial sub-tree swaps (e.g. dropping a component into a slot
          // re-renders only that node's subtree) insert new nested
          // .db-dropzone elements into an existing, already-initialized root
          // that the scan above skips - leaving the freshly placed
          // component's own slots with no Sortable, so nothing can be dragged
          // into them. Re-scan the enclosing root so those get a Sortable
          // too; setDropzone()/Sortable.get() skip already-managed nodes.
          const root = event.target?.closest?.('.db-dropzone--root');
          if (root) {
            setDropzone(root);
          }
        });
      });

      // Destroy the Sortable instance of any dropzone htmx removes from the
      // document. SortableJS keeps every instance in a module-level registry
      // until destroy() is called, and its document-level dragover scan
      // (installed by emptyInsertThreshold) consults that whole registry on
      // every pointer move - a detached instance is an empty zero-rect list
      // the scan can still hand the drag to, making the dragged element jump
      // into a container that isn't on the page. Since every drop re-renders
      // the panels via htmx swaps, the registry otherwise grows by one full
      // panel's worth of stale instances per drop, degrading every drag
      // after the first. htmx:beforeCleanupElement is fired once per element
      // of a swapped-out subtree (recursively, before detach, so it still
      // bubbles here), for every swap style, OOB fragments included -
      // unlike htmx:beforeSwap, it never fires for elements that survive
      // the swap (e.g. the target of a hx-swap="none" request).
      once('dbDropzoneCleanup', 'body', context).forEach((body) => {
        body.addEventListener('htmx:beforeCleanupElement', (event) => {
          const element = event.target;
          if (element.classList?.contains('db-dropzone')) {
            Sortable.get(element)?.destroy();
          }
        });
      });
    },
  };
})(Drupal, once, Sortable);
