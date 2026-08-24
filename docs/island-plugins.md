# Extend Display Builder with island plugins

> The general idea of an “Islands” architecture is deceptively simple: render HTML pages on the server, and inject placeholders or slots around highly dynamic regions […] that can then be “hydrated” on the client into small self-contained widgets, reusing their server-rendered initial HTML.

[Astro documentation](https://docs.astro.build/en/concepts/islands/)

Display Builder extensively uses HTMX's [out-of-band swapping](https://htmx.org/attributes/hx-swap-oob/) to allow an event triggered from an island to also update other islands.

There are 6 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they are displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel
- `Menu` items: they are displayed in the contextual menu triggered with right-click
- `Contextual` panels: They are displayed tabbed into the contextual sidebar
- `Floating` controls: they float over one or more `View` panels, only visible while an attached panel is the active main tab

Visual positioning:

![Islands region](images/islands-regions.webp)

## Interface

Notable methods:

- `IslandInterface::build()`: Build the renderable content of the island from state data. This renderable can be annotated by `HtmxEvents` to trigger HTTP requests
- Everything from `IslandEventSubscriberInterface`: each method is an HTMX event managed by `ApiController`. Islands receive callbacks for every state-changing operation:

  | Method | Triggered when |
  |---|---|
  | `onAttachToRoot()` | A node is added at root level |
  | `onAttachToSlot()` | A node is added into a component slot |
  | `onMove()` | An existing node is moved |
  | `onUpdate()` | A node's source configuration is updated |
  | `onDelete()` | A node is removed |
  | `onActive()` | A node becomes the focused node |
  | `onHistoryChange()` | The history pointer changes (undo / redo) |
  | `onPresetSave()` | A node is saved as a reusable preset |

  There are similar methods for `ApiPublishingController`, which are related to action managed by DisplayBuildable plugins:

  | Method | Triggered when |
  |---|---|
  | `onPublish()` | The builder state is published to the permanent storage (config or content entity) |
  | `onRestore()` | The builder is restored to its last published state |
  | `onRevert()` | An entity view override is reverted to the base display config |

  The default base class (`IslandPluginBase`) returns an empty array for all events. Use `IslandReloadEventsTrait` if your island needs to do a full re-render on history/restore/revert changes.
- `PluginFormInterface::buildConfigurationForm()`: to make the island plugin configurable in the [Display Builder profile (config entity)](configuration.md)

HTMX behavior will change according to `IslandInterface::build()` return value:

- `null`: the island is not altered
- `empty`: the island is emptied

### Attributes

- `id`: Plugin ID
- `label`: The human-readable name of the plugin. It is not derived from
  the ID and does not have to match it: the panels shipped by default are
  labelled Canvas (`builder`), Scaffold (`scaffold`) and Navigator (`tree`).
  Always reference a panel by its **ID** in code, config and `attach_to`.
- `description`: A brief description of the plugin.
- `type` : The island type from enumeration.
- `region`: The region the island renders in, for the types split into
  several: `sidebar` or `main` for `IslandType::View`, `start` or `end` for
  `IslandType::Button`. Structural, never a profile preference, since the
  regions of a type are built differently from one another. An island omitting
  it falls back to `IslandType::defaultRegion()`, `main` and `end`.
- `attach_to`: For `IslandType::Floating` islands only: the plugin IDs of
  the View panels this floating control attaches to. It renders once per
  listed panel, alongside that panel's own content, and is only visible
  while that panel's main tab is active. Fixed by the plugin definition,
  not admin-configurable.

Example:

```php
namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

#[Island(
  id: 'block_library',
  label: new TranslatableMarkup('Blocks library'),
  description: new TranslatableMarkup('List of available Drupal blocks to use.'),
  type: IslandType::Library,
)]
class BlockLibraryPanel extends IslandPluginBase {
}
```

A `Floating` island whose `build()` is just a small cluster of icon buttons
can use `IslandFloatingControlsTrait::buildControlButtons()` instead of
assembling a button group by hand:

```php
namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandFloatingControlsTrait;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

#[Island(
  id: 'highlight',
  label: new TranslatableMarkup('Highlight'),
  description: new TranslatableMarkup('Highlight builder zones to ease drag and move around.'),
  type: IslandType::Floating,
  attach_to: ['builder'],
)]
class HighlightToggle extends IslandPluginBase {

  use IslandFloatingControlsTrait;

  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    return $this->buildControlButtons([
      'highlight' => [
        'icon' => 'border',
        'tooltip' => $this->t('Highlight builder zones to ease drag and move around.'),
        'attribute' => 'data-set-highlight',
        'library' => 'display_builder/highlight',
      ],
    ]);
  }

}
```

An island needing a richer control (e.g. a dropdown) doesn't need this
trait at all - `ProfileViewBuilder` only requires `build()` to return a
renderable, and positions it the same way regardless.

### Keyboard support

We provide a small utility to map buttons to keyboard shortcuts to ease actions in the Display Builder.

If your Island provide a button you should use our method `\Drupal\display_builder\RenderableBuilderTrait::buildButton()` to generate your button.
This method allow a keyboard mapping as parameter. Check the class for more info.

If your island is of type `IslandType::View`, implement the `keyboardShortcuts()` method.

_Note_: There is no control on duplicate, please ensure your shortcut is not already used.

A shortcut is declared as a canonical **combo** string: optional modifier tokens
then the key, joined with `+`, in the fixed order `mod`, `alt`, `shift`, `key`
— for example `b`, `shift+p`, `mod+z`, `mod+shift+z`, `Delete`. The `mod` token
is the per-platform primary accelerator, **Cmd on macOS and Ctrl everywhere
else**, so one declaration works on both an Apple and a PC keyboard
(@see `components/display_builder/js/keyboard.js`). A single button may list
several combos separated by spaces (e.g. `mod+z u`); the first is the one shown
in the help dialog.

Shortcuts taken by the islands shipped with the module:

| Key | Island |
|---|---|
| `c` | Canvas |
| `g` | Scaffold |
| `n` | Navigator |
| `p` | Preview |
| `l` | Libraries |
| `o` | Logs |
| `mod+z` (or `u`) | Undo |
| `mod+shift+z` (or `r`) | Redo |
| `shift+p` | Publish |
| `shift+e` | Expand |
| `mod+c` / `mod+v` / `mod+d` | Copy / Paste / Duplicate selected |
| `Delete` | Remove selected |

The contextual shortcuts (`mod+c`/`mod+v`/`mod+d`/`Delete`) act on the currently
selected node without opening the right-click menu; they resolve the node's slot
context the same way the menu does (@see `components/display_builder/js/keyboard.js`).