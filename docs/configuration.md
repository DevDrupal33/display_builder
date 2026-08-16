# Configuring

You need `display_builder_ui` sub-module.

Permissions:

- "Administer display builder"

## List of Display Builder profiles

`/admin/structure/display-builder`

![Display Builder profiles](images/configs.webp)

## Configuration of a single Display Builder profile (config entity)

Each Display Builder profile is a configuration entity with:

- Metadata: a label and a description
- The islands configuration, by type

There are 6 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they are displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel
- `Menu` items: they are displayed in the contextual menu triggered with right-click
- `Contextual` panels: They are displayed tabbed into the contextual sidebar
- `Floating` controls: they float over one or more `View` panels (e.g. the Canvas or Preview panel), only visible while an attached panel is the active main tab

Visual positioning:

![Islands region](images/islands-regions.webp)

Each island can be:

- enabled or not
- ordered relatively to the other ones of a same type
- configured one by one

For example, here is the configuration of Toolbar buttons, where some are configurable:

![Islands configuration](images/config-1.webp)

Configuration happens in a modal:

![Islands modal](images/config-modal.webp)

![Islands configuration](images/config-2.webp)

Islands have no region choice: whether a `View` panel is a main area tab or a
sidebar drawer, and whether a `Button` sits at the start or the end of the
toolbar, is fixed by the plugin itself, because the regions of a type are built
differently from one another (a narrow drawer versus a full width tab), not
because one is preferred over the other.

A type split into several regions is configured as one table per region: an
island can be reordered inside its region, never moved out of it.

`Floating` controls are the same: which panel(s) they attach to is fixed by the
plugin itself (not admin-configurable, since a floating control is usually
built for a specific panel's own layout/CSS) - no region choice, just
enable/disable and (if two or more attach to the same panel) reordering.

## Access & permissions

Each Display Builder profile is associated to a permission:

![Permissions](images/permissions.webp)

This is conditioning the builders available in the selector for a specific user:

![Configuration selector](images/selector.webp)

## See also

- [Available islands](islands.md) - Learn about built-in UI islands
- [Extend with island plugins](island-plugins.md) - Create custom islands
- [Entity displays](entity-displays.md) - Example of Display Builder usage
- [Page layouts](page-layout.md) - Another example of Display Builder usage
