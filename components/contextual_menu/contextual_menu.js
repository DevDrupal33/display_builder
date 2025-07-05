/**
 * @file
 * Contextual menu for Display Builder.
 *
 * @todo missing some aria-expanded attribute?
 */
/* cspell:ignore uidom */
/* eslint no-use-before-define: 0 */
/* eslint camelcase: 0 */
/* eslint no-unused-expressions: 0 */
/* eslint no-unused-vars: 0 */
((Drupal, once, { computePosition, offset, shift, flip }) => {
  /**
   * Initialize display builder contextual menu.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behaviors for display builder contextual menu functionality.
   */
  Drupal.behaviors.displayBuilderContextualMenu = {
    attach(context) {
      once('dbContextualMenu', '.display-builder', context).forEach(
        (builder) => {
          alterHtmxEvents(builder);
        },
      );
      // Enable context menu only on IslandType::View.
      once('dbIslandContextualMenu', '.db-island-view', context).forEach(
        (island) => {
          const menu = document.querySelector('.db-context-menu');
          if (!menu) return;
          initContextMenu(menu.dataset.dbId, menu, island);
        },
      );
    },
  };

  /**
   * Sets up event listeners for HTMX requests on a builder element.
   *
   * @param {HTMLElement} builder - The builder element to attach events to
   * @listens htmx:configRequest
   * @listens htmx:afterRequest
   */
  function alterHtmxEvents(builder) {
    builder.addEventListener('htmx:configRequest', (event) => {
      // Handle context menu path, a data attribute allow to find only the
      // contextual menu.
      // The menu is built with a placeholder for the instance id and slot_id as
      // we do not know yet what will be clicked. Slot id can be null, not
      // instance id. They are set in the handleContextMenu().
      // @see src/Plugin/display_builder/Island/Menu.php
      if (!event.target.dataset?.contextualMenu) return;

      let instanceId = event.target.dataset?.instanceId;
      if (!instanceId) return;

      let _parent_id = '__none__';
      let _slot_id = '__none__';
      let _slot_position = 0;

      if (event.target.dataset.contextualAction === 'paste') {
        _parent_id = event.target.dataset.slotInstanceId;
        instanceId = event.target.dataset.copyInstanceId;
        _slot_id = event.target.dataset.slotId;
        _slot_position = event.target.dataset?.slotPosition ?? 0;
      }

      if (event.target.dataset.contextualAction === 'duplicate') {
        _parent_id = event.target.dataset.slotInstanceId;
        _slot_id = event.target.dataset?.slotId;
        // On duplicate, slot position is 0 to ease.
        // @todo move to last position?
        _slot_position = event.target.dataset?.slotPosition ?? 0;
      }

      event.detail.path = event.detail.path.replace(
        '__instance_id__',
        instanceId,
      );
      event.detail.path = event.detail.path.replace(
        '__parent_id__',
        _parent_id,
      );
      event.detail.path = event.detail.path.replace('__slot_id__', _slot_id);
      event.detail.path = event.detail.path.replace(
        '__slot_position__',
        _slot_position,
      );
    });

    builder.addEventListener('htmx:afterRequest', (event) => {
      // Handle contextual menu hiding after click.
      if (!event.target.dataset?.contextualMenu) return;
      const menu = event.target.closest('sl-menu');
      if (!menu) return;
      menu.style.display = 'none';
    });
  }

  /**
   * Init the context menu for a island.
   *
   * @param {string} builderId - The builder id.
   * @param {HTMLElement} menu - The menu element.
   * @param {HTMLElement} island - The island element.
   */
  function initContextMenu(builderId, menu, island) {
    island.addEventListener('contextmenu', (event) => {
      event.preventDefault();
      handleContextMenu(builderId, event, menu);
    });
    setupGlobalClickHandler(menu);
  }

  /**
   * Handle the context menu event.
   *
   * @param {string} builderId - The builder id.
   * @param {MouseEvent} event - The context menu event.
   * @param {HTMLElement} menu - The context menu element.
   */
  function handleContextMenu(builderId, event, menu) {
    // Not on an instance, hide the menu.
    const instance = getInstance(event.target);
    if (!instance.id) {
      menu.style.display = 'none';
      return;
    }

    setupMenuSelectHandler(builderId, menu);

    const slotData = getSlotData(event.target, instance);
    setMenuLabel(menu, gePathLabel(event.target));

    const copyInstance = Drupal.displayBuilder.LocalStorageManager.get(
      builderId,
      'copy',
      { id: null, title: null },
    );
    updateMenuItems(menu, instance, slotData, copyInstance);

    updateMenuPosition(menu, event.clientX, event.clientY);

    // insertDebugInfo(menu, instance, slotData, copyInstance);
  }

  /**
   * Inserts a <pre><code> element with the debug information before the closing </sl-menu>.
   *
   * @param {HTMLElement} menu - The context menu element.
   * @param {Object} instance - The instance object.
   * @param {Object} slotData - The slot object.
   * @param {Object} copyInstance - The copy instance object.
   */
  function insertDebugInfo(menu, instance, slotData, copyInstance) {
    // Check if the debug element already exists and remove it to avoid duplication.
    const existingDebug = menu.querySelector('pre.debug-info');
    if (existingDebug) {
      existingDebug.remove();
    }

    // Create the <pre><code> element.
    const pre = document.createElement('pre');
    pre.classList.add('debug-info');
    const code = document.createElement('code');

    // Add debug information.
    let debugData = `<strong>Instance:</strong>${JSON.stringify(instance, null, 2)} `;
    if (slotData?.id) {
      debugData += `Slot Data:</strong>${JSON.stringify(slotData, null, 2)}`;
    }
    if (copyInstance?.id || copyInstance?.title) {
      debugData += `<strong>Copy Instance:</strong>${JSON.stringify(copyInstance, null, 2)}`;
    }
    code.innerHTML = debugData;

    pre.appendChild(code);

    // Insert the <pre><code> element before the closing </sl-menu>.
    menu.appendChild(pre);
  }

  /**
   * Update the position of the context menu.
   *
   * @param {HTMLElement} menu - The context menu element.
   * @param {number} clientX - The X-coordinate of the click event.
   * @param {number} clientY - The Y-coordinate of the click event.
   */
  function updateMenuPosition(menu, clientX, clientY) {
    menu.style.display = 'block';
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
    computePosition(virtualEl, menu, {
      middleware: [
        offset({ mainAxis: 5, alignmentAxis: 4 }),
        flip({
          fallbackPlacements: ['left-start'],
        }),
        shift({ padding: 10 }),
      ],
      placement: 'right-start',
    }).then(({ x, y }) => {
      Object.assign(menu.style, {
        left: `${x}px`,
        top: `${y}px`,
      });
    });
  }

  /**
   * Try to generate the current instance path name for menu label.
   *
   * @param {HTMLElement} target - The DOM element to extract instance data from.
   * @return {string} - The path.
   */
  function gePathLabel(target) {
    const name = [];

    const currentInstance = target.closest('[data-instance-id]');
    const parentId =
      currentInstance.closest('[data-slot-title]')?.dataset?.instanceId;

    if (parentId) {
      const parentInstanceName = currentInstance.closest(
        `[data-instance-title][data-instance-id="${parentId}"]`,
      )?.dataset?.instanceTitle;
      name.push(formatName(parentInstanceName));
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
      name.push(formatName(instanceTitle));
    }

    const slotPosition = currentInstance.dataset?.slotPosition;
    if (slotPosition) {
      name.push(parseInt(slotPosition, 10) + 1);
    }

    return name.join(' / ');
  }

  /**
   * Formats a given string by replacing underscores with spaces and capitalizing
   * the first letter of the string.
   *
   * @param {string} name - The string to format.
   * @return {string} The formatted string with the first letter capitalized and
   * underscores replaced by spaces. Returns an empty string if the input is falsy.
   */
  function formatName(name) {
    name = name.replace('_', ' ');
    return name ? name[0].toUpperCase() + name.slice(1) : '';
  }

  /**
   * Get the instance ID from the target element or its closest ancestor.
   *
   * @param {HTMLElement} target - The DOM element to extract instance data from.
   * @return {Object} An object containing the instance data:
   *   - `id` {string|null}: The value of the `data-instance-id` attribute, or `null` if not found.
   *   - `title` {string|null}: The value of the `data-instance-title` attribute of the element, or `null` if not found.
   */
  function getInstance(target) {
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

    // Look for parent only.
    // @todo exclude itself?
    const parent = target.closest(
      `[data-instance-id]:not([data-instance-id="${target.dataset.instanceId}"])`,
    );
    // const parent = target.closest('[data-instance-id]');
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
   * Retrieves slot data from a given DOM element or its closest ancestor with a `data-slot-id` attribute.
   *
   * @param {HTMLElement} target - The DOM element to extract slot data from.
   * @param {Object|null} currentInstance - The instance object.
   * @return {Object} An object containing the slot data:
   *   - `id` {string|null}: The value of the `data-slot-id` attribute, or `null` if not found.
   *   - `title` {string|null}: The `title` attribute of the element, or `null` if not found.
   *   - `instanceId` {string|null}: The instance id of the slot, or `null` if not found.
   */
  function getSlotData(target, currentInstance) {
    const name = [];
    if (target.dataset?.slotId) {
      // Click on a slot without position mean we need to look for parent.
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

    // Look for parent first.
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

      // We have a position, need to find the parent instance.
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
            formatName(currentInstance.title),
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

    // Look for child.
    const children = target.querySelector('[data-slot-id]');
    if (children && children?.dataset?.slotId) {
      currentInstance.isChild
        ? ''
        : name.push(Drupal.t('Base'), formatName(currentInstance.title));
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

  /**
   * Update the menu label based on the current context.
   *
   * @param {HTMLElement} menu - The context menu element.
   * @param {string} dataLabel - The label to set.
   */
  function setMenuLabel(menu, dataLabel) {
    const menuLabel = menu.querySelector('sl-menu-label');
    if (menuLabel) {
      menuLabel.textContent = dataLabel;
    }
  }

  /**
   * Update the menu items based on the current context.
   *
   * @param {HTMLElement} menu - The context menu element.
   * @param {Object} instance - An object containing the instance data with id and title.
   * @param {Object} slotData - An object containing the slot data with keys id and title.
   * @param {Object} copyInstance - The instance stored in local storage with keys id and title.
   */
  function updateMenuItems(menu, instance, slotData, copyInstance) {
    const copyMenu = menu.querySelector('sl-menu-item[value="copy"]');
    const pasteMenu = menu.querySelector('sl-menu-item[value="paste"]');

    pasteMenu ? (pasteMenu.disabled = !copyInstance?.id) : '';
    copyMenu ? (copyMenu.disabled = copyInstance?.id === instance.id) : '';

    menu.setAttribute('data-instance-id', instance.id);

    menu.querySelectorAll('sl-menu-item').forEach((item) => {
      // This is very important to add information to the menu so we know what
      // to do when clicked, these values will be replaced in the htmx url.
      item.setAttribute('data-instance-title', formatName(instance.title));
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
              '!label': formatName(instance.title),
            });
          } else {
            item.textContent = Drupal.t('Copy !label', {
              '!label': formatName(instance.title),
            });
          }
          break;

        case 'paste':
          if (copyInstance?.id) {
            item.textContent = Drupal.t('Paste !label', {
              '!label': formatName(copyInstance.title),
            });
          }
          break;

        case 'duplicate':
          item.textContent = Drupal.t('Duplicate !label', {
            '!label': formatName(instance.title),
          });
          break;

        case 'remove':
          item.textContent = Drupal.t('Remove !label', {
            '!label': formatName(instance.title),
          });
          break;

        default:
          break;
      }

      item.checked = false;
    });
  }

  /**
   * Set up the menu select handler for the context menu.
   *
   * @param {string} builderId - The builder id.
   * @param {HTMLElement} menu - The context menu element.
   */
  function setupMenuSelectHandler(builderId, menu) {
    menu.addEventListener('sl-select', (menuEvent) => {
      const { item } = menuEvent.detail;
      if (item.checked) {
        if (item.value === 'copy') {
          Drupal.displayBuilder.LocalStorageManager.set(builderId, 'copy', {
            id: item.dataset.instanceId,
            title: item.dataset.instanceTitle ?? '',
          });
        }

        menu.style.display = 'none';
        item.checked = false;
      }
    });
  }

  /**
   * Set up a global click handler to close the context menu.
   *
   * @param {HTMLElement} menu - The context menu element.
   */
  function setupGlobalClickHandler(menu) {
    document.addEventListener('click', (event) => {
      if (!event.target.dataset.instanceId) {
        menu.style.display = 'none';
      }
    });
  }
})(Drupal, once, FloatingUIDOM);
