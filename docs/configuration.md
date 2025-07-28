# Configuring

You need `display_builder_ui`.

Permissions:

- "Administer display builder"

## List of Display builder profiles

`/admin/structure/display-builder`

![Display builder profiles](images/configs.webp)

## Configuration of a single Display builder profile (config entity)

Each Display builder profile is a configuration entity with:

- Metadata: a label and a description
- The islands configuration, by type

There are 5 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they a re displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel
- `Menu` items: they are displayed in the contextual menu triggered with right-click
- `Contextual` panels: They are displayed tabbed into the contextual sidebar

Visual positioning:

![Islands region](images/islands-regions.webp)

Each island can be:

- enabled or not
- ordered relatively to the other ones of a same type
- configured one by one

For example, here is the configuration of View panels:

![Islands configuration](images/config.webp)

View panels have an extra feature, they can be moved between 2 different regions:

- tabbed in the center of the toolbar
- or as buttons in the start of the toolbar

> 🚧 2025-07-01: "Edit" button for configurable islands is not implemented yet. See [#3529067](https://www.drupal.org/project/display_builder/issues/3529067)

## Access & permissions

Each Display builder profile is associated to a permission:

![Permissions](images/permissions.webp)

This is conditioning the builders available in the selector for a specific user:

![Configuration selector](images/selector.webp)

> 🚧 2025-07-01: Not implemented yet. See [#3529129](https://www.drupal.org/project/display_builder/issues/3529129)
