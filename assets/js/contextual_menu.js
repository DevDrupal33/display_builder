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
 * @class
 *
 * @example
 * // Usage example:
 * const menu = new ContextualMenu(islandElement, floatingUIOptions, menu, true);
 *
 * @see https://www.drupal.org/docs/develop/javascript-api/javascript-api-overview
 *
 * @param {HTMLElement} island
 *   The DOM element representing the builder "island" to attach the menu to.
 * @param {Object} options
 *   Options for FloatingUIDOM positioning middleware.
 * @param {HTMLElement} menu
 *   The DOM element representing the contextual menu.
 * @param {boolean} debug
 *   Whether to enable debug messages.
 *
 * @prop {HTMLElement} island
 *   The builder island element.
 * @prop {Object} options
 *   FloatingUIDOM options for menu positioning.
 * @prop {HTMLElement} menu
 *   The contextual menu element.
 * @prop {boolean} debug
 *   Debug flag.
 * @prop {string} builderId
 *   The builder instance ID, extracted from the menu dataset.
 * @prop {Array<Object>} plugins
 *   Registered plugin objects with hooks.
 */
class ContextualMenu {
  constructor(island, options, menu, debug) {
    this.island = island;
    this.options = options;
    this.menu = menu;
    this.debug = debug;
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
   * @listens contextmenu
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
    this.setMenuLabel(this.gePathLabel(event.target));

    const copyInstance = Drupal.displayBuilder.LocalStorageManager.get(
      this.builderId,
      'copy',
      { id: null, title: null },
    );
    this.updateMenuItems(instance, slotData, copyInstance);

    this.updateMenuPosition(event.clientX, event.clientY);

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
   */
  updateMenuItems(instance, slotData, copyInstance) {
    const copyMenu = this.menu.querySelector('.menu__item[value="copy"]');
    const pasteMenu = this.menu.querySelector('.menu__item[value="paste"]');

    pasteMenu ? (pasteMenu.disabled = !copyInstance?.id) : '';
    copyMenu ? (copyMenu.disabled = copyInstance?.id === instance.id) : '';

    this.menu.setAttribute('data-instance-id', instance.id);

    this.menu.querySelectorAll('.menu__item').forEach((item) => {
      item.setAttribute('data-instance-title', this.formatName(instance.title));
      item.setAttribute('data-instance-id', instance.id);
      item.setAttribute('data-slot-id', slotData?.id ?? '__root__');
      item.setAttribute('data-slot-position', slotData?.position ?? 0);
      item.setAttribute(
        'data-slot-instance-id',
        slotData?.instanceId ?? '__root__',
      );
      if (copyInstance?.id) {
        item.setAttribute('data-copy-instance-id', copyInstance.id);
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
            this.builderId,
            'copy',
            {
              id: item.dataset.instanceId,
              title: item.dataset.instanceTitle ?? '',
            },
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
   * @listens click
   */
  setupGlobalClickHandler() {
    document.addEventListener('click', (event) => {
      if (!event.target.dataset.instanceId) {
        this.menu.style.display = 'none';
      }
    });
  }

  // --- Utility and data extraction methods below ---

  /**
   * Generates a hierarchical label path for a given DOM target element based on its
   * data attributes and its relationship to parent elements.
   *
   * @param {HTMLElement} target
   *   The DOM element for which to generate the path label.
   * @return {string}
   *   The formatted path label representing the element's hierarchy.
   */
  gePathLabel(target) {
    const name = [];
    const currentInstance = target.closest('[data-instance-id]');
    const parentId =
      currentInstance.closest('[data-slot-title]')?.dataset?.instanceId;

    if (parentId) {
      const parentInstanceName = currentInstance.closest(
        `[data-instance-title][data-instance-id="${parentId}"]`,
      )?.dataset?.instanceTitle;
      name.push(this.formatName(parentInstanceName));
    } else {
      name.push(Drupal.t('Base'));
    }

    const slotTitle =
      currentInstance.closest('[data-slot-title]')?.dataset?.slotTitle;
    if (slotTitle) {
      name.push(slotTitle);
    }

    const instanceTitle = currentInstance.dataset?.instanceTitle;
    if (instanceTitle) {
      name.push(this.formatName(instanceTitle));
    }

    const slotPosition = currentInstance.dataset?.slotPosition;
    if (slotPosition) {
      name.push(parseInt(slotPosition, 10) + 1);
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
   * Retrieves contextual information about a DOM element instance, such as its ID, title, position,
   * parent instance details, and whether it is a child instance.
   *
   * @param {HTMLElement} target
   *   The DOM element to extract instance information from.
   * @return {Object}
   *   An object containing the following properties:
   *   @prop {string|null} id
   *     The instance ID of the target or its parent, or null if not found.
   *   @prop {number|null} position
   *     The slot position of the instance, or null if not applicable.
   *   @prop {string|null} title
   *     The title of the instance, or null if not found.
   *   @prop {string|null} parentId
   *     The instance ID of the parent, or null if not found.
   *   @prop {string|null} parentTitle
   *     The title of the parent instance, or null if not found.
   *   @prop {boolean} isChild
   *     Whether the instance is considered a child.
   */
  getInstance(target) {
    let isChild = false;
    let title = '';
    if (target?.dataset?.instanceId) {
      if (!target.dataset.instanceTitle) {
        title = target.closest('[data-instance-title]')?.dataset?.instanceTitle;
        isChild = true;
      } else {
        title = target.dataset.instanceTitle;
      }

      const parent = target.closest(
        `[data-instance-id]:not([data-instance-id="${target.dataset.instanceId}"])`,
      );

      return {
        id: target.dataset.instanceId,
        position: target?.dataset?.slotPosition ?? 1,
        title,
        parentId: parent?.dataset?.instanceId ?? null,
        parentTitle: parent?.dataset?.instanceTitle ?? null,
        isChild,
      };
    }

    const parent = target.closest(
      `[data-instance-id]:not([data-instance-id="${target.dataset.instanceId}"])`,
    );
    if (parent?.dataset?.instanceId) {
      if (!parent.dataset.instanceTitle) {
        title = parent.closest('[data-instance-title]')?.dataset?.instanceTitle;
        isChild = true;
      } else {
        title = parent.dataset.instanceTitle;
      }

      let position = null;
      if (target?.dataset?.slotPosition) {
        position = parseInt(target.dataset.slotPosition, 10) + 1;
      }

      const grandParent = parent.closest(
        `[data-instance-id]:not([data-instance-id="${parent.dataset.instanceId}"])`,
      );

      return {
        id: parent.dataset.instanceId,
        position,
        title,
        parentId: grandParent?.dataset?.instanceId ?? null,
        parentTitle: grandParent?.dataset?.instanceTitle ?? null,
        isChild,
      };
    }

    return {
      id: null,
      title: null,
      position: null,
      isChild,
    };
  }

  /**
   * Retrieves slot-related data from a given DOM target element and the current instance.
   *
   * This function inspects the target element and its ancestors/descendants to extract
   * information about slots and instances, such as their IDs, titles, positions, and names.
   * It handles different scenarios depending on the presence of specific data attributes.
   *
   * @param {HTMLElement} target
   *   The DOM element from which to extract slot data.
   * @param {Object} currentInstance
   *   The current instance context, containing at least a `title`, `id`, and optionally `isChild` and `position`.
   * @param {string} currentInstance.title
   *   The title of the current instance.
   * @param {string|number} [currentInstance.id]
   *   The ID of the current instance.
   * @param {boolean} [currentInstance.isChild]
   *   Whether the current instance is a child.
   * @param {number|string} [currentInstance.position]
   *   The position of the current instance.
   * @return {Object} An object containing slot and instance data:
   * @return {string} [return.name] - The constructed name/path for the slot.
   * @return {string|null} [return.id] - The slot ID, or null if not found.
   * @return {string|null} [return.title] - The slot title, or null if not found.
   * @return {number} [return.position] - The slot position (1-based), or 0 if not found.
   * @return {string|null} [return.instanceId] - The instance ID, or null if not found.
   * @return {string|null} [return.instanceTitle] - The instance title, or null if not found.
   */
  getSlotData(target, currentInstance) {
    const name = [];
    if (target.dataset?.slotId) {
      const { slotId } = target.dataset;
      const slotTitle = target.dataset?.slotTitle ?? '';
      const { instanceId } = target.dataset;
      let instanceTitle = target.dataset?.instanceTitle;
      if (!instanceTitle) {
        instanceTitle = target.closest(
          `[data-instance-title][data-instance-id="${target.dataset.instanceId}"]`,
        )?.dataset?.instanceTitle;
      }
      name.push(instanceTitle, slotTitle);

      return {
        name: name.join(' / '),
        id: slotId,
        title: slotTitle ?? '',
        position: target.dataset?.slotPosition
          ? parseInt(target.dataset.slotPosition, 10) + 1
          : 0,
        instanceId,
        instanceTitle,
      };
    }

    const parent = target.closest(
      `[data-slot-id]:not([data-instance-id="${target.dataset.instanceId}"])`,
    );
    if (parent && parent?.dataset?.slotId) {
      let instanceTitle = parent.dataset?.instanceTitle;
      let position = target.dataset?.slotPosition;
      if (!position) {
        position =
          target.closest(`[data-slot-position]`)?.dataset?.slotPosition;
      }

      if (!instanceTitle && position) {
        const parentInstance = target.closest(
          `[data-instance-title]:not([data-instance-id="${target.dataset.instanceId}"])`,
        );
        instanceTitle = parentInstance
          ? parentInstance?.dataset?.instanceTitle
          : null;
      }

      currentInstance.isChild
        ? ''
        : name.push(
            instanceTitle,
            parent.dataset?.slotTitle ?? '',
            this.formatName(currentInstance.title),
            parseInt(position, 10) + 1,
          );

      return {
        name: name.join(' / '),
        id: parent.dataset.slotId,
        title: parent.dataset?.slotTitle ?? '',
        position: position ? parseInt(position, 10) + 1 : 0,
        instanceId: parent.dataset.instanceId,
        instanceTitle,
      };
    }

    const children = target.querySelector('[data-slot-id]');
    if (children && children?.dataset?.slotId) {
      currentInstance.isChild
        ? ''
        : name.push(Drupal.t('Base'), this.formatName(currentInstance.title));
      if (currentInstance?.position) {
        name.push(parseInt(currentInstance.position, 10) + 1);
      }

      return {
        name: name.join(' / '),
        id: children.dataset.slotId,
        title: children.dataset?.slotTitle ?? '',
        position: target.dataset?.slotPosition
          ? parseInt(target.dataset.slotPosition, 10) + 1
          : 0,
        instanceId: currentInstance?.id,
      };
    }

    return {
      id: null,
      title: null,
      position: 0,
      instanceId: null,
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
 * @param {boolean} debug
 *   Whether to enable debug messages.
 * @listens htmx:configRequest
 * @listens htmx:afterRequest
 */
Drupal.displayBuilder.menuAlterHtmxEvents = (builder, debug) => {
  builder.addEventListener('htmx:configRequest', (event) => {
    if (!event.target.dataset?.contextualMenu) return;

    let instanceId = event.target.dataset?.instanceId;
    if (!instanceId) return;

    let parentId = '__none__';
    let slotId = '__none__';
    let slotPosition = 0;

    if (event.target?.value === 'paste') {
      parentId = event.target.dataset.slotInstanceId;
      instanceId = event.target.dataset.copyInstanceId;
      slotId = event.target.dataset.slotId;
      slotPosition = event.target.dataset?.slotPosition ?? 0;
    }

    if (event.target?.value === 'duplicate') {
      parentId = event.target.dataset.slotInstanceId;
      slotId = event.target.dataset?.slotId;
      if (
        event.target.dataset?.slotPosition &&
        event.target.dataset.slotPosition > 0
      ) {
        slotPosition = event.target.dataset.slotPosition - 1;
      }
    }

    event.detail.path = event.detail.path.replace(
      '__instance_id__',
      instanceId,
    );
    event.detail.path = event.detail.path.replace('__parent_id__', parentId);
    event.detail.path = event.detail.path.replace('__slot_id__', slotId);
    event.detail.path = event.detail.path.replace(
      '__slot_position__',
      slotPosition,
    );

    if (debug) console.log(`[menu] configRequest: ${event.detail.path}`);
  });

  builder.addEventListener('htmx:afterRequest', (event) => {
    if (!event.target.dataset?.contextualMenu) return;
    const menu = event.target.closest('.db-menu');
    if (!menu) return;
    menu.style.display = 'none';
  });
};
