# Available islands

There are 7 types of islands:

- `View` panels: shown as a tab in the main area, or as a drawer in the sidebar
- `Preview`: a single panel rendering the display as visitors see it, pinned beside the active main-area tab by the toolbar's preview toggle
- `Button`s: toolbar buttons, at the start or the end of the toolbar
- `Library` panels: tabs of the Libraries sidebar panel
- `Contextual` panels: tabs of the contextual sidebar, shown while a component or block is selected
- `Menu` items: entries of the contextual menu, opened with a right-click
- `Floating` controls: attached to one or more main-area panels, only visible while one of them is the active pane

Visual positioning:

![Islands region](images/islands-regions.webp)

Display Builder is shipped with those ones by default:

## View panels

### For the sidebar

#### Instances

List all displays available to build and jump to any of them.

#### Navigator

Hierarchical view of components and blocks.

![Navigator](images/islands/tree.webp)

#### Libraries

Pick elements from libraries and drop them in the display.

By default, 3 libraries are available:

- Components for SDC
- Block for all other data sources for slots (field, blocks, WYSIWYG...)
- Presets for pattern presets

![Library](images/islands/library.webp)

### For the main area

#### Canvas

The Display Builder main island. Build the display with dynamic preview.

![Canvas](images/islands/builder.webp)

#### Scaffold

Schematic hierarchical view of elements without preview.

![Scaffold](images/islands/scaffold.webp)

The schematic view is better for dropping components and blocks when the
preview in the canvas panel is making things complicated.

Both Canvas and Scaffold share the same root dropzone. When nothing has
been built yet, it shows a hint pointing to the Library panel instead of
sitting there as a near-invisible empty strip.
For examples: a modal, a sliding slider, a collapsing accordion is hard to
manipulate when built.

A configurable allowlist of layout components (e.g. Bootstrap grid rows) is
rendered with its real output instead, so the actual layout nesting is visible
at a glance. Everything else stays a plain wireframe card.

Which components count as "layout" is theme-specific, hence a configurable
list rather than a hardcoded one — set it in the island's configuration form.

#### Logs

Logs based on changes history. Disabled by default.

![Logs](images/islands/logs.webp)

> 🚧 2026-08-25: Revisions are currently limited to 20.

## Preview

Show a real time preview of the display, as visitors see it, without the
builder chrome.

![Preview](images/islands/preview.webp)

Preview is not a main-area tab. The toolbar's preview toggle (shortcut `p`)
pins it beside the active editor pane, Canvas or Scaffold, and a handle
between the two sets how much room each gets. Drag the handle all the way,
double-click it, or press `Home` on it, and Preview takes the full width;
the tab strip hides with the editor, and a panel shortcut (`c`, `g`) brings
the editor back. The handle is keyboard operable: arrows resize, `Home`
collapses the editor, `End` maximizes it, `Enter` or `Space` toggles. The
split and its ratio are remembered per builder in your browser.

## Buttons

How a toolbar button looks is fixed by the island that builds it, not by the
profile: Back, Expand, Theme, Help, Undo and Redo are icons, everything else
is a label. Whether a button shows is the status of the island providing it, so an
action nobody should reach is turned off in one place instead of being hidden
in the interface while its endpoint stays open.

### At the start of the toolbar

#### Save status

A small indicator confirming the last action reached the stored state.

### At the end of the toolbar

#### History

Undo and redo changes.

![History](images/islands/history.webp)

> 🚧 2026-08-25: Revisions are currently limited to 20.

#### State

Publish and reset the display.

![State](images/islands/state.webp)

With 3 available buttons:

- Publish
- Restore (Restore to last published version)
- Revert (for entity view override only, revert to default display for this entity)

Revert is the one the profile can turn off, being a power user action.

#### Expand

Expand the builder to cover the current viewport.

![Controls](images/islands/controls.webp)

#### Theme

Pick a theme mode as light/dark/system for the display builder. Disabled by
default.

#### Help

Information about the available keyboard shortcuts.

#### Real-time collaboration

See [real-time collaboration documentation](realtime-collaboration.md).
Disabled by default.

![Real-time collaboration](images/islands/collaboration.webp)

#### Back

Exit the display builder and go back to admin UI.

![Back](images/islands/back.webp)

## Library panels

### Components

List of available components (SDC).

![Components library](images/islands/component_library.webp)

A specific configuration allow to pick available components.

### Blocks

List of available blocks.

![Blocks library](images/islands/block_library.webp)

### Presets

See [patterns presets documentation](pattern-presets.md).

## Menu items

Available on secondary click on a block, component or slot, in Canvas or Scaffold panels:

![Contextual menu](images/islands/menu.webp)

The menu title is the block, or component with tree position.

### Main menu items

Copy, paste and duplicate the selected block or component.

### Styles menu

Copy, paste, merge and delete the styles of the selected block or component.

### Preset

Save as a preset. See also: [patterns presets documentation](pattern-presets.md).

### Delete

Remove a component or block with all children.

## Contextual panels

### Config

Configure the active component or block.

### Styles

Apply style utilities to the active component or block. Needs the
[UI Styles](https://www.drupal.org/project/ui_styles) module.

### Design tokens

Override CSS variables for the active component or block. Needs the
[UI Skins](https://www.drupal.org/project/ui_skins) module. Disabled by
default.

### Visibility

Set visibility conditions for the active component or block.

## Floating controls

A floating control is attached to one or more main-area panels and is only
visible while one of them is the active pane. Unlike toolbar buttons, it
lives with a specific panel because it is usually meaningless anywhere else
(e.g. it depends on CSS scoped to that panel). It renders either in the
header row of its pane, or as a small box pinned to the top-left of the
pane, below the toolbar.

### Highlight

Highlight zones to ease drag and move around. Attached to the Canvas and
Scaffold panels, where highlighting has a visible effect, as a dropdown of
independent toggles: drop zones, components, blocks and visual spacing.

### Responsive width and Zoom

Switch the Preview between breakpoint widths to check responsive behavior,
and scale it to 25, 50, 75 or 100% to see a wide layout whole. Sits in the
Preview pane's header.

![Viewport switcher](images/islands/viewport.webp)

### Chrome indicator

Shows, in the Preview pane's header, whether the preview renders wrapped in
the site's page chrome or bare. See
[How displays nest](how-displays-nest.md) for what decides it.

## See also

- [Configuring Display Builder](configuration.md) - Configure islands in profiles
- [Extend with island plugins](island-plugins.md) - Create custom islands
- [Pattern presets](pattern-presets.md) - Save and reuse component arrangements
- [Real-time collaboration](realtime-collaboration.md) - Work together with other
  editors
