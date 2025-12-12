# Available islands

There are 5 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they a re displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel.
- `Menu` items: they are displayed in the contextual menu triggered with right-click.
- `Contextual` panels: They are displayed tabbed into the contextual sidebar.

Visual positioning:

![Islands region](images/islands-regions.webp)

Display Builder is shipped with those ones by default:

## View panels

### Libraries

Pick elements from libraries and drop them in the display.

By default, 3 libraries are available:

- Components for SDC
- Block for all other data sources for slots (field, blocks, WYSIWYG...)
- Presets for pattern presets

![Library](images/islands/library.webp)

### Builder

The Display Builder main island. Build the display with dynamic preview.

![Builder](images/islands/builder.webp)

### Layers

Manage hierarchical layer view of elements without preview.

![Layers](images/islands/layers.webp)

Layers is better for dropping components and blocks when the preview in the builder panel is making thinks complicated. For examples: a modal, a sliding slider, a collapsing accordion...

### Tree

Hierarchical view of components and blocks.

![Library](images/islands/tree.webp)

### Preview

Show a real time preview of the display.

![Preview](images/islands/preview.webp)

### Logs

Logs based on changes history.

![Logs](images/islands/logs.webp)

> 🚧 2025-11-09: History steps are currently limited to 10.

## Buttons

When a buttons island is made of proper button component it is possible to configure for each of them:

- to be displayed with label and icon
- to be displayed with label only
- to be displayed with icon only
- to not be displayed

### History

Undo and redo changes.

![History](images/islands/history.webp)

> 🚧 2025-11-09: History steps are currently limited to 10.

### State

Publish and reset the display.

![State](images/islands/state.webp)

With 3 available buttons:

- Publish
- Restore (Restore to last published version)
- Revert (for entity view override only, revert to default display for this entity)

### Controls

Control the building experience.

![Controls](images/islands/controls.webp)

### Real-time collaboration

See [real-time collaboration documentation](realtime-collaboration.md).

![Preview](images/islands/back.webp)

### Viewport switcher

Change main region width according to breakpoints.

![Preview](images/islands/viewport.webp)

### Back

Exit the display builder and go back to admin UI.

![Preview](images/islands/back.webp)

## Library panels

### Components

List of available components (SDC).

![Preview](images/islands/component_library.webp)

A specific configuration allow to pick available components.

### Blocks

List of available blocks.

![Preview](images/islands/block_library.webp)

### Presets

See [patterns presets documentation](pattern-presets.md).

## Menu items

Available on secondary click on a block, component or slot, in Builder or Layer panels:

![Logs](images/islands/menu.webp)

The menu title is the block, or component with tree position.

### Preset

See also: [patterns presets documentation](pattern-presets.md).

### Delete

Remove a component or block with all children.

## Contextual panels

### Contextual form

Configure the active component or block.

### Styles

Apply style utilities to the active component or block.

### Skins

Override CSS variables for the active component or block.

### Visibility

Set visibility conditions for the active component or block.
