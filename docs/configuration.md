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

There are 5 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they are displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel
- `Menu` items: they are displayed in the contextual menu triggered with right-click
- `Contextual` panels: They are displayed tabbed into the contextual sidebar

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

Some islands can be moved between 2 different regions. For example, View Panels can be:

- tabbed in the center of the toolbar
- or as buttons in the start of the toolbar

And Toolbar Buttons can be moved from one side to the other of the toolbar.

![Islands configuration](images/config-2.webp)

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
