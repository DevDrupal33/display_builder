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

There are 7 types of islands:

- `View` panels: shown as a tab in the main area, or as a drawer in the sidebar
- `Preview`: a single panel rendering the display as visitors see it, pinned beside the active main-area tab by the toolbar's preview toggle
- `Button`s: toolbar buttons, at the start or the end of the toolbar
- `Library` panels: tabs of the Libraries sidebar panel
- `Contextual` panels: tabs of the contextual sidebar, shown while a component or block is selected
- `Menu` items: entries of the contextual menu, opened with a right-click
- `Floating` controls: attached to one or more main-area panels (e.g. the Canvas or the Preview panel), only visible while one of them is the active pane

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
