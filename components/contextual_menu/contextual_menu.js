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
    this.setMenuLabel(this.getPathLabel(event.target));

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
   * Compensates for a Shoelace `<sl-menu-item>` submenu positioning bug.
   *
   * Confirmed live, not assumed: a submenu's `<sl-popup>` (internal to
   * `<sl-menu-item>`, used for any menu item rendered with a `submenu`
   * slot, e.g. "Styles" - @see components/shoelace/menu_item/menu_item.twig)
   * computes its horizontal position as if it needed to subtract
   * `.display-builder`'s own X offset from the viewport's left edge (the
   * Drupal admin sidebar's width), then applies the result as
   * `position: fixed` (genuinely viewport-relative, confirmed no
   * transform/filter/zoom anywhere in the ancestor chain that would
   * legitimately justify that). The two don't match: the computed
   * coordinate assumes one reference frame, the applied positioning
   * strategy uses another - a real Shoelace quirk with this specific
   * doubly-nested-in-shadow-DOM submenu structure, not something in this
   * module's own CSS. Confirmed via direct measurement: the error is
   * always exactly `.display-builder`'s own `getBoundingClientRect().x`
   * (272px in one measured case, matched to the sub-pixel against the
   * known-correct position) - not a guess or approximation.
   *
   * Fix works *with* Shoelace's own calculation instead of patching the
   * DOM after the fact: `<sl-popup>`'s own `distance` property is a
   * normal input to its internal Floating UI `offset` middleware
   * (added on top of whatever the base placement computes, right or
   * wrong) - setting it to `.display-builder`'s current X offset cancels
   * the bug at the source. Verified stable across 5 repeated
   * `.reposition()` calls (Shoelace's own `autoUpdate` continuously
   * recomputes position on resize/scroll) - a post-hoc DOM patch
   * instead would have been overwritten by the very next recompute
   * tick, fighting that loop instead of feeding it a correct input.
   *
   * Applied fresh on every menu open (not once at setup) since
   * `.display-builder`'s own offset can change between openings - the
   * Drupal admin sidebar can be toggled collapsed/expanded.
   */
  fixSubmenuPositioning() {
    const displayBuilder = this.island.closest('.display-builder');
    if (!displayBuilder) return;

    const offsetX = displayBuilder.getBoundingClientRect().x;

    this.menu.querySelectorAll('sl-menu-item').forEach((item) => {
      if (!item.querySelector('sl-menu[slot="submenu"]')) return;
      const popup = item.shadowRoot?.querySelector('sl-popup');
      if (popup) popup.distance = offsetX;
    });
  }

  /**
   * Sets the text content of the menu label element.
   *
   * @param {string} dataLabel
   *   The label text to display in the menu.
   */
  setMenuLabel(dataLabel) {
    const menuLabel = this.menu.querySelector('.db-menu__label');
    if (menuLabel) {
      menuLabel.textContent = dataLabel;
    }
  }

  /**
   * Updates the contextual menu items based on the current instance, slot data, and copy instance.
   *
   * - Enables or disables the "copy" and "paste" menu items depending on the state of the copy instance.
   * - Sets various data attributes on each menu item for use in event handlers or UI updates.
   * - Updates the text content of menu items to reflect the current action and instance.
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

      switch (item.value) {
        case 'copy':
          if (copyMenu.disabled) {
            item.textContent = Drupal.t('Copied (!label)', {
              '!label': this.formatName(instance.title),
            });
          } else {
            item.textContent = Drupal.t('Copy !label', {
              '!label': this.formatName(instance.title),
            });
          }
          break;
        case 'paste':
          if (copyInstance?.id) {
            item.textContent = Drupal.t('Paste !label', {
              '!label': this.formatName(copyInstance.title),
            });
          }
          break;
        case 'duplicate':
          item.textContent = Drupal.t('Duplicate !label', {
            '!label': this.formatName(instance.title),
          });
          break;
        case 'remove':
          item.textContent = Drupal.t('Remove !label', {
            '!label': this.formatName(instance.title),
          });
          break;
        case 'copy_styles':
          item.textContent = copyStylesMenu.disabled
            ? Drupal.t('Copied')
            : Drupal.t('Copy');
          break;
        case 'paste_styles':
          if (copyStylesInstance?.id) {
            item.textContent = Drupal.t('Paste !label', {
              '!label': this.formatName(copyStylesInstance.title),
            });
          }
          break;
        case 'merge_styles':
          if (copyStylesInstance?.id) {
            item.textContent = Drupal.t('Merge !label', {
              '!label': this.formatName(copyStylesInstance.title),
            });
          }
          break;
        case 'delete_styles':
          item.textContent = Drupal.t('Delete');
          break;
        default:
          break;
      }
      item.checked = false;
    });
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
        this.menu.style.display = 'none';
        item.checked = false;
      }
    });
  }

  /**
   * Set up a global click handler to close the context menu.
   *
   * @listens event:click
   */
  setupGlobalClickHandler() {
    document.addEventListener('click', (event) => {
      if (!event.target.dataset.nodeId) {
        this.menu.style.display = 'none';
      }
    });
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
   * Generates a hierarchical label path for a given DOM target element based
   * on its data attributes and its relationship to parent elements.
   *
   * @param {HTMLElement} target
   *   The DOM element for which to generate the path label.
   * @return {string}
   *   The formatted path label representing the element's hierarchy.
   */
  getPathLabel(target) {
    const node = target.closest('[data-node-id]');
    const slot = node?.closest('[data-slot-title]');
    const name = [];

    name.push(
      slot ? this.formatName(slot.dataset.nodeTitle) : Drupal.t('Base'),
    );
    if (slot?.dataset.slotTitle) name.push(slot.dataset.slotTitle);
    if (node?.dataset.nodeTitle) {
      name.push(this.formatName(node.dataset.nodeTitle));
    }
    if (node?.dataset.slotPosition) {
      name.push(parseInt(node.dataset.slotPosition, 10) + 1);
    }

    return name.join(' / ');
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
