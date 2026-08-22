/* cspell:ignore UIDOM */
/* eslint no-unused-expressions: 0 */
/* eslint no-console: 0 */
/* eslint class-methods-use-this: 0 */
/**
 * Provides a contextual menu for a display builder "island" element.
 *
 * This class manages the display and interaction of a contextual menu
 * for a given builder element, supporting plugin hooks, menu item updates,
 * and integration with Drupal's LocalStorageManager for copy/paste actions.
 *
 * Menu accept plugins with the following hook:
 * - onMenuOpen(menuInstance, instance, slotData, copyInstance)
 *
 * @class
 *
 * @example
 * // Usage example:
 * const menu = new ContextualMenu(islandElement, floatingUIOptions, menu, true);
 *
 * @prop {HTMLElement} island
 *   The builder island element.
 * @prop {Object} options
 *   FloatingUIDOM options for menu positioning.
 * @prop {HTMLElement} menu
 *   The contextual menu element.
 * @prop {string} builderId
 *   The builder instance ID, extracted from the menu dataset.
 * @prop {Array<Object>} plugins
 *   Registered plugin objects with hooks.
 */
class ContextualMenu {
  constructor(island, options, menu) {
    this.island = island;
    this.options = options;
    this.menu = menu;
    this.builderId = menu?.dataset?.dbId;
    this.plugins = [];
    if (!this.menu || !this.builderId) return;

    this.initContextMenu();
  }

  /**
   * Register a plugin object with hooks.
   * @param {Object} plugin
   *   The plugin to register.
   */
  registerPlugin(plugin) {
    this.plugins.push(plugin);
  }

  /**
   * Initialize context menu event listeners.
   *
   * @listens event:contextmenu
   */
  initContextMenu() {
    this.island.addEventListener('contextmenu', (event) => {
      event.preventDefault();
      this.handleContextMenu(event);
    });
    this.setupGlobalClickHandler();
    this.setupEscapeHandler();

    const closeButton = this.menu.querySelector('.db-menu__close');
    if (closeButton) {
      closeButton.addEventListener('click', () => {
        this.menu.style.display = 'none';
      });
    }
  }

  /**
   * Closes the menu on Escape, ahead of everything else Escape closes.
   *
   * Escape already walks a chain - Settings drawer, then Libraries drawer
   * (@see components/display_builder/js/sidebar.js) - and the menu belongs at
   * the head of it: it is the most recent thing the user opened and the one
   * they mean to dismiss. Hence a capture-phase listener on `document`, which
   * runs before any bubble-phase handler there whatever order they registered
   * in, and stops the event only when there is actually a menu to close, so
   * an Escape with the menu shut still reaches the drawers.
   *
   * Bound on `document` rather than on the menu: nothing focuses the menu
   * when it opens, so a listener on the menu itself only ever fired for a
   * user who had arrow-keyed into the items.
   *
   * @listens event:keydown
   */
  setupEscapeHandler() {
    document.addEventListener(
      'keydown',
      (event) => {
        if (event.key !== 'Escape') return;
        if (this.menu.style.display !== 'block') return;

        this.menu.style.display = 'none';
        event.stopPropagation();
      },
      true,
    );
  }

  /**
   * Handle the context menu event.
   * @param {MouseEvent} event
   *   The mouse event.
   */
  handleContextMenu(event) {
    const instance = this.getInstance(event.target);
    if (!instance.id) {
      this.menu.style.display = 'none';
      return;
    }

    this.setupMenuSelectHandler();

    const slotData = this.getSlotData(event.target, instance);
    const { name, path } = this.getPathParts(event.target);
    this.setMenuLabel(name, path);

    const copyInstance = Drupal.displayBuilder.LocalStorageManager.get(
      'copy',
      { id: null, title: null },
      this.builderId,
    );
    const copyStylesInstance = Drupal.displayBuilder.LocalStorageManager.get(
      'copyStyles',
      { id: null, title: null },
      this.builderId,
    );
    this.updateMenuItems(instance, slotData, copyInstance, copyStylesInstance);

    this.updateMenuPosition(event.clientX, event.clientY);
    this.fixSubmenuPositioning();

    // Call plugin hooks for openMenu.
    this.plugins.forEach((plugin) => {
      if (typeof plugin.onMenuOpen === 'function') {
        plugin.onMenuOpen(this, instance, slotData, copyInstance);
      }
    });
  }

  /**
   * Updates the position of the contextual menu based on the given client coordinates.
   *
   * @param {number} clientX
   *   The X coordinate (in client space) where the menu should appear.
   * @param {number} clientY
   *   The Y coordinate (in client space) where the menu should appear.
   */
  updateMenuPosition(clientX, clientY) {
    this.menu.style.display = 'block';
    const { computePosition, offset, shift, flip } = this.options;
    const virtualEl = {
      getBoundingClientRect() {
        return {
          width: 0,
          height: 0,
          x: clientX,
          y: clientY,
          top: clientY,
          left: clientX,
          right: clientX,
          bottom: clientY,
        };
      },
    };
    computePosition(virtualEl, this.menu, {
      middleware: [
        offset({ mainAxis: 5, alignmentAxis: 4 }),
        flip({ fallbackPlacements: ['left-start'] }),
        shift({ padding: 10 }),
      ],
      placement: 'right-start',
    }).then(({ x, y }) => {
      Object.assign(this.menu.style, {
        left: `${x}px`,
        top: `${y}px`,
      });
    });
  }

  /**
   * Compensates for how Floating UI detects a containing block.
   *
   * A submenu's `<sl-popup>` - internal to `<sl-menu-item>`, used for any
   * item rendered with a `submenu` slot, e.g. "Styles", @see
   * components/shoelace/menu_item/menu_item.twig - is hardcoded
   * `strategy="fixed"` by Shoelace's SubmenuController.
   *
   * Floating UI's `isContainingBlock()` treats any element with
   * `container-type` other than `normal` as a containing block for fixed
   * descendants, and subtracts its offset from the coordinates it returns.
   * `.display-builder` carries `container-type: inline-size` (@see
   * components/toolbar/toolbar.css). The browser disagrees: measured with a
   * `position: fixed; top: 0; left: 0` probe appended to `.display-builder`,
   * which renders at viewport 0,0 - so it resolves the popup's `left`/`top`
   * against the viewport after all. The submenu therefore lands short by
   * exactly `.display-builder`'s own `getBoundingClientRect()`, on both
   * axes, and the fix is to add that back.
   *
   * Do not "fix" this by dropping the `container-type`. That was tried and
   * reverted: it is load-bearing for drag and drop. Without it Firefox
   * failed 11 Playwright drag tests deterministically while Chromium passed
   * them all, because a dropzone was no longer the topmost element at the
   * drop point. Neither `isolation: isolate` nor `position: relative` nor
   * `contain: layout` nor `contain: inline-size` substitutes - each was
   * measured against that suite and left it red. Switching the popup to
   * `strategy="absolute"` does not position correctly either.
   *
   * The compensation is a `translate` on the popup, and both halves matter:
   *
   * - *A transform, not `distance`/`skidding`.* Those two are Floating UI
   *   offsets along the placement's own main and cross axes, so their sign
   *   follows the placement. The submenu flips to `left-start` when it is
   *   near the right edge of the viewport, and a compensation expressed that
   *   way flips with it, landing the submenu twice as far off in the
   *   opposite direction - which is exactly how this was found. `translate`
   *   is in absolute page axes and is unaffected by the flip.
   * - *Re-applied on `sl-reposition`.* `<sl-popup>` runs Floating UI's
   *   `autoUpdate`, which recomputes `left`/`top` on every scroll and
   *   resize, and emits that event once it has. `.display-builder`'s own
   *   offset changes on exactly those occasions, so the correction has to be
   *   recomputed with them or the submenu drifts away as the page scrolls
   *   under an open menu. Shoelace never writes `translate`, so this does
   *   not fight `autoUpdate`: it rides it.
   *
   * The listener is attached once per popup and left in place - the menu is
   * a single element reused for every right-click, so there is nothing to
   * tear down and no accumulation.
   */
  fixSubmenuPositioning() {
    this.menu.querySelectorAll('sl-menu-item').forEach((item) => {
      if (!item.querySelector('sl-menu[slot="submenu"]')) return;
      const popup = item.shadowRoot?.querySelector('sl-popup');
      if (!popup) return;

      if (!popup.dataset.dbOffsetFix) {
        popup.dataset.dbOffsetFix = 'true';
        popup.addEventListener('sl-reposition', () =>
          this.offsetSubmenuPopup(popup),
        );
      }
      this.offsetSubmenuPopup(popup);
    });
  }

  /**
   * Adds back the offset Floating UI wrongly subtracted from one submenu.
   *
   * @param {HTMLElement} popup
   *   The `<sl-popup>` inside a menu item's shadow root.
   */
  offsetSubmenuPopup(popup) {
    const displayBuilder = this.island.closest('.display-builder');
    const inner = popup.shadowRoot?.querySelector('[part~="popup"]');
    if (!displayBuilder || !inner) return;

    const { x, y } = displayBuilder.getBoundingClientRect();
    inner.style.translate = `${x}px ${y}px`;
  }

  /**
   * Sets the two lines of the menu header: the element, then where it sits.
   *
   * Both grow with the content - a node title is free text and the path grows
   * with nesting depth - so CSS truncates them with an ellipsis and the whole
   * of both is kept on the title attribute.
   *
   * @param {string} name
   *   The name of the element the menu acts on.
   * @param {string} path
   *   Its location in the hierarchy.
   */
  setMenuLabel(name, path) {
    const menuLabel = this.menu.querySelector('.db-menu__label');
    if (!menuLabel) return;

    const nameElement = menuLabel.querySelector('.db-menu__name');
    const pathElement = menuLabel.querySelector('.db-menu__path');
    if (nameElement) nameElement.textContent = name;
    if (pathElement) pathElement.textContent = path;
    menuLabel.title = `${name}\n${path}`;
  }

  /**
   * Labels a menu item, keeping the full text reachable when it is truncated.
   *
   * @param {HTMLElement} item
   *   The `<sl-menu-item>` to label.
   * @param {string} label
   *   The label text.
   */
  setItemLabel(item, label) {
    item.textContent = label;
    item.title = label;
  }

  /**
   * Updates the contextual menu items based on the current instance, slot data, and copy instance.
   *
   * - Enables or disables the "copy" and "paste" menu items depending on the state of the copy instance.
   * - Sets various data attributes on each menu item for use in event handlers or UI updates.
   * - Names the clipboard contents on the paste and merge items, and the
   *   target on the destructive one.
   *
   * @param {Object} instance
   *   The current instance object.
   * @param {Object} slotData
   *   The slot data object, may be null or undefined.
   * @param {Object} copyInstance
   *   The instance object being copied, if any.
   * @param {Object} copyStylesInstance
   *   The instance object whose styles are being copied, if any.
   */
  updateMenuItems(instance, slotData, copyInstance, copyStylesInstance) {
    const copyMenu = this.menu.querySelector('.menu__item[value="copy"]');
    const pasteMenu = this.menu.querySelector('.menu__item[value="paste"]');
    const copyStylesMenu = this.menu.querySelector(
      '.menu__item[value="copy_styles"]',
    );
    const pasteStylesMenu = this.menu.querySelector(
      '.menu__item[value="paste_styles"]',
    );
    const mergeStylesMenu = this.menu.querySelector(
      '.menu__item[value="merge_styles"]',
    );

    pasteMenu ? (pasteMenu.disabled = !copyInstance?.id) : '';
    copyMenu ? (copyMenu.disabled = copyInstance?.id === instance.id) : '';
    pasteStylesMenu ? (pasteStylesMenu.disabled = !copyStylesInstance?.id) : '';
    mergeStylesMenu ? (mergeStylesMenu.disabled = !copyStylesInstance?.id) : '';
    copyStylesMenu
      ? (copyStylesMenu.disabled = copyStylesInstance?.id === instance.id)
      : '';

    this.menu.setAttribute('data-node-id', instance.id);
    this.updateStylesSource(copyStylesInstance);

    this.menu.querySelectorAll('.menu__item').forEach((item) => {
      item.setAttribute('data-node-title', this.formatName(instance.title));
      item.setAttribute('data-node-id', instance.id);
      item.setAttribute('data-slot-id', slotData?.id ?? '__root__');
      item.setAttribute('data-slot-position', slotData?.position ?? 0);
      item.setAttribute(
        'data-slot-node-id',
        instance.parentId ?? slotData?.nodeId ?? '__root__',
      );
      if (copyInstance?.id) {
        item.setAttribute('data-copy-instance-id', copyInstance.id);
      }
      if (copyStylesInstance?.id) {
        item.setAttribute('data-copy-styles-node-id', copyStylesInstance.id);
      }

      // The header already names the element every action applies to, so the
      // items keep the plain verb the plugin rendered (@see
      // src/Plugin/display_builder/Island/Menu.php and its siblings). Only
      // two are rewritten: paste, which acts on what is on the clipboard
      // rather than on anything visible, and remove, the single destructive
      // action, where spelling out the target is worth the width it costs.
      // The Styles submenu names its own clipboard once in its heading
      // instead, @see updateStylesSource().
      switch (item.value) {
        case 'paste':
          this.setItemLabel(
            item,
            copyInstance?.id
              ? Drupal.t('Paste !label', {
                  '!label': this.formatName(copyInstance.title),
                })
              : Drupal.t('Paste'),
          );
          break;
        case 'remove':
          this.setItemLabel(
            item,
            Drupal.t('Remove !label', {
              '!label': this.formatName(instance.title),
            }),
          );
          break;
        default:
          break;
      }

      // Which element is on the clipboard, shown as state rather than by
      // renaming a command while the user is reading it: the two copy items
      // are disabled precisely when they are the source, so the check mark
      // marks that one and nothing else. Every other item is left unchecked -
      // a checked item is how the select handler recognizes an action.
      item.checked =
        item.disabled &&
        (item.value === 'copy' || item.value === 'copy_styles');
    });
  }

  /**
   * Names the styles clipboard in the Styles submenu, or hides that it exists.
   *
   * Both entries ship hidden from the server (@see MenuStyles::build()): the
   * clipboard lives in localStorage, so whether there is anything to paste is
   * only knowable here, at the moment the menu opens.
   *
   * @param {Object} copyStylesInstance
   *   The instance whose styles are on the clipboard, if any.
   */
  updateStylesSource(copyStylesInstance) {
    const source = this.menu.querySelector('.db-menu__styles-source');
    const forget = this.menu.querySelector('.db-menu__styles-forget');
    const copied = Boolean(copyStylesInstance?.id);

    if (source) {
      source.textContent = copied
        ? Drupal.t('Copied from !label', {
            '!label': this.formatName(copyStylesInstance.title),
          })
        : '';
      source.hidden = !copied;
    }
    if (forget) {
      forget.hidden = !copied;
    }
  }

  /**
   * Set up menu select handler.
   *
   * @listens shoelace:sl-select
   */
  setupMenuSelectHandler() {
    // Specific shoelace event handler.
    // @todo avoid using this kind of specific.
    this.menu.addEventListener('sl-select', (menuEvent) => {
      const { item } = menuEvent.detail;
      if (item.checked) {
        if (item.value === 'copy') {
          Drupal.displayBuilder.LocalStorageManager.set(
            'copy',
            {
              id: item.dataset.nodeId,
              title: item.dataset.nodeTitle ?? '',
            },
            this.builderId,
          );
        }
        if (item.value === 'copy_styles') {
          Drupal.displayBuilder.LocalStorageManager.set(
            'copyStyles',
            {
              id: item.dataset.nodeId,
              title: item.dataset.nodeTitle ?? '',
            },
            this.builderId,
          );
        }
        // Nothing to ask the server: the styles clipboard is this entry and
        // nothing else, so forgetting it is removing it.
        if (item.value === 'forget_styles') {
          Drupal.displayBuilder.LocalStorageManager.remove(
            'copyStyles',
            this.builderId,
          );
        }
        this.menu.style.display = 'none';
        item.checked = false;
      }
    });
  }

  /**
   * Set up a global click handler to close the context menu.
   *
   * Bound in the capture phase, so the menu closes even for a click an island
   * swallows before it can bubble up to here - the Navigator does exactly that
   * (@see components/panel_tree/panel_tree.js, which stops row clicks in
   * capture to keep them away from HTMX). Capture runs outermost first, so
   * `document` is reached before any island listener regardless.
   *
   * @listens event:click
   */
  setupGlobalClickHandler() {
    document.addEventListener(
      'click',
      (event) => {
        if (!event.target.dataset.nodeId) {
          this.menu.style.display = 'none';
        }
      },
      true,
    );
  }

  // --- Utility and data extraction methods below ---
  //
  // Every node wrapper (component/block) carries `data-node-id` +
  // `data-node-title` together, and every slot wrapper carries
  // `data-slot-id`/`data-slot-title` + the *parent's* `data-node-id`/
  // `data-node-title` together.
  // @see BuilderPanel::buildNodeAttributes()
  // @see BuilderPanel::buildSlotAttributes()
  // That co-location is what lets the three methods below resolve context
  // with a couple of `.closest()` calls instead of separately re-deriving
  // it with per-case fallback chains.

  /**
   * Names a target element and locates it in the hierarchy.
   *
   * The two are returned apart because the menu header shows them as two
   * lines: what you clicked, then where it sits. The position closes the
   * path rather than joining the name - among identical siblings it is the
   * only thing that tells them apart, and that is a fact about the place,
   * not about the element.
   *
   * @param {HTMLElement} target
   *   The DOM element for which to generate the label.
   * @return {Object}
   *   `{name, path}`, both already formatted for display.
   */
  getPathParts(target) {
    const node = target.closest('[data-node-id]');
    const slot = node?.closest('[data-slot-title]');
    const path = [];

    path.push(
      slot ? this.formatName(slot.dataset.nodeTitle) : Drupal.t('Base'),
    );
    if (slot?.dataset.slotTitle) path.push(slot.dataset.slotTitle);
    if (node?.dataset.slotPosition) {
      path.push(parseInt(node.dataset.slotPosition, 10) + 1);
    }

    return {
      name: node?.dataset.nodeTitle
        ? this.formatName(node.dataset.nodeTitle)
        : Drupal.t('Element'),
      path: path.join(' / '),
    };
  }

  /**
   * Formats a given name by replacing underscores with spaces and capitalizing the first letter.
   *
   * @param {string} name
   *   The name string to format.
   * @return {string}
   *   The formatted name, or an empty string if no name is provided.
   */
  formatName(name) {
    if (!name) return '';
    name = name.replace('_', ' ');
    return name[0].toUpperCase() + name.slice(1);
  }

  /**
   * Resolves the node instance for a right-clicked (or otherwise targeted)
   * DOM element - the nearest `[data-node-id]` ancestor, whether that's a
   * component/block itself or the slot it's sitting in.
   *
   * @param {HTMLElement} target
   *   The DOM element to extract instance information from.
   * @return {Object}
   *   `{id, title, position, parentId, parentTitle}` - `id`/`title`/
   *   `parentId`/`parentTitle` are null if no ancestor carries
   *   `data-node-id`.
   */
  getInstance(target) {
    const node = target.closest('[data-node-id]');

    if (!node) {
      return {
        id: null,
        title: null,
        position: null,
        parentId: null,
        parentTitle: null,
      };
    }

    const parent = this.closestOtherNode(node);

    return {
      id: node.dataset.nodeId,
      title: node.dataset.nodeTitle ?? '',
      position: node.dataset.slotPosition ?? 1,
      parentId: parent?.dataset?.nodeId ?? null,
      parentTitle: parent?.dataset?.nodeTitle ?? null,
    };
  }

  /**
   * Finds the nearest ancestor carrying `data-node-id` other than `el`'s
   * own node - used to walk from a node up to its parent in the tree.
   *
   * @param {HTMLElement} el
   *   The element to start climbing from (its own `data-node-id` is used
   *   as the exclusion, not matched against).
   * @return {HTMLElement|null}
   *   The matched ancestor, or null.
   */
  closestOtherNode(el) {
    return (
      el.parentElement?.closest(
        `[data-node-id]:not([data-node-id="${el.dataset.nodeId}"])`,
      ) ?? null
    );
  }

  /**
   * Retrieves slot-related data for a right-clicked (or otherwise targeted)
   * DOM element - which slot a paste/duplicate action would land in.
   *
   * Three cases, depending on where `target` sits relative to slot
   * wrappers:
   * 1. `target` is itself a slot wrapper (right-clicked directly on one).
   * 2. `target` lives inside a slot (right-clicked a component/block
   *    that's nested in one).
   * 3. `target` is a container whose own slot is empty - offer that slot
   *    as the paste/duplicate destination.
   *
   * @param {HTMLElement} target
   *   The DOM element from which to extract slot data.
   * @param {Object} currentInstance
   *   The result of `getInstance(target)`.
   * @return {Object}
   *   `{name, id, title, position, nodeId, instanceTitle}`, all null
   *   (except `position`, `0`) if `target` has no slot in either direction.
   */
  getSlotData(target, currentInstance) {
    const name = [];

    // Case 1: target itself is a slot.
    if (target.dataset?.slotId) {
      name.push(target.dataset.nodeTitle, target.dataset.slotTitle ?? '');

      return {
        name: name.join(' / '),
        id: target.dataset.slotId,
        title: target.dataset.slotTitle ?? '',
        position: target.dataset?.slotPosition
          ? parseInt(target.dataset.slotPosition, 10) + 1
          : 0,
        nodeId: target.dataset.nodeId,
        instanceTitle: target.dataset.nodeTitle,
      };
    }

    // Case 2: target lives inside a slot.
    const slot = target.closest('[data-slot-id]');
    if (slot) {
      const position =
        target.dataset?.slotPosition ??
        target.closest('[data-slot-position]')?.dataset?.slotPosition;

      name.push(
        slot.dataset.nodeTitle,
        slot.dataset?.slotTitle ?? '',
        this.formatName(currentInstance.title),
        parseInt(position, 10) + 1,
      );

      return {
        name: name.join(' / '),
        id: slot.dataset.slotId,
        title: slot.dataset?.slotTitle ?? '',
        position: position ? parseInt(position, 10) + 1 : 0,
        nodeId: slot.dataset.nodeId,
        instanceTitle: slot.dataset.nodeTitle,
      };
    }

    // Case 3: target is a container with its own (empty) slot.
    const child = target.querySelector('[data-slot-id]');
    if (child) {
      name.push(Drupal.t('Base'), this.formatName(currentInstance.title));
      if (currentInstance?.position) {
        name.push(parseInt(currentInstance.position, 10) + 1);
      }

      return {
        name: name.join(' / '),
        id: child.dataset.slotId,
        title: child.dataset?.slotTitle ?? '',
        position: target.dataset?.slotPosition
          ? parseInt(target.dataset.slotPosition, 10) + 1
          : 0,
        nodeId: currentInstance?.id,
        instanceTitle: currentInstance?.title,
      };
    }

    return {
      id: null,
      title: null,
      position: 0,
      nodeId: null,
      instanceTitle: null,
    };
  }
}

// Expose to Drupal namespace.
Drupal.displayBuilder = Drupal.displayBuilder || {};
Drupal.displayBuilder.ContextualMenu = ContextualMenu;

/**
 * Sets up event listeners for HTMX requests on a builder element.
 *
 * @param {Object} builder
 *   The builder.
 *
 * @listens htmx:configRequest
 * @listens htmx:afterRequest
 */
Drupal.displayBuilder.menuAlterHtmxEvents = (builder) => {
  builder.addEventListener('htmx:configRequest', (event) => {
    if (!event.target.dataset?.contextualMenu) return;

    let nodeId = event.target.dataset?.nodeId;
    if (!nodeId) return;

    const { value } = event.target;

    // Styles actions target the right-clicked node itself (its own
    // third_party_settings.styles), not a slot to attach into, so they
    // don't share the node/parent/slot resolution below.
    // @see \Drupal\display_builder\HtmxEvents::onClickPasteStyles()
    if (value === 'paste_styles' || value === 'merge_styles') {
      Object.assign(event.detail.parameters, {
        node_id: nodeId,
        source_node_id: event.target.dataset.copyStylesNodeId,
        mode: value === 'merge_styles' ? 'merge' : 'replace',
      });
      return;
    }

    if (value === 'delete_styles') {
      event.detail.parameters.node_id = nodeId;
      return;
    }

    let parentId = '__none__';
    let slotId = '__none__';
    let slotPosition = 0;

    if (value === 'paste') {
      // parentId must be the slot's actual owner (data-slot-node-id), not
      // the right-clicked node's own id (data-node-id) - the two only
      // coincide when the right-clicked node is itself the slot owner.
      // Using the wrong one silently attaches the pasted copy under a
      // node that never renders it, since only components that actually
      // declare that slot get walked for rendering.
      parentId = event.target.dataset.slotNodeId;
      nodeId = event.target.dataset.copyInstanceId;
      slotId = event.target.dataset.slotId;
      slotPosition = event.target.dataset?.slotPosition ?? 0;
    }

    if (value === 'duplicate') {
      parentId = event.target.dataset.slotNodeId;
      slotId = event.target.dataset?.slotId;
      if (
        event.target.dataset?.slotPosition &&
        event.target.dataset.slotPosition > 0
      ) {
        slotPosition = event.target.dataset.slotPosition - 1;
      }
    }

    // The contextual menu is a single shared DOM element reused for every
    // right-click, so these values are only known now, not when the menu
    // item's hx-post URL was rendered - htmx lets us add them here instead
    // of relying on route path placeholders.
    // @see \Drupal\display_builder\HtmxEvents::onClickPaste()
    Object.assign(event.detail.parameters, {
      node_id: nodeId,
      parent_id: parentId,
      slot_id: slotId,
      slot_position: slotPosition,
    });
  });

  builder.addEventListener('htmx:afterRequest', (event) => {
    if (!event.target.dataset?.contextualMenu) return;
    const menu = event.target.closest('.db-menu');
    if (!menu) return;
    menu.style.display = 'none';
  });
};
