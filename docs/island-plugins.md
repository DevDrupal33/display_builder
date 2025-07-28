# Extend Display Builder with island plugins

> The general idea of an “Islands” architecture is deceptively simple: render HTML pages on the server, and inject placeholders or slots around highly dynamic regions […] that can then be “hydrated” on the client into small self-contained widgets, reusing their server-rendered initial HTML.

[Astro documentation](https://docs.astro.build/en/concepts/islands/)

Display Builder extensively uses HTMX's [out-of-band swapping](https://htmx.org/attributes/hx-swap-oob/) to allow an event triggered from an island to also update other islands.

There are 5 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they a re displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel
- `Menu` items: they are displayed in the contextual menu triggered with right-click
- `Contextual` panels: They are displayed tabbed into the contextual sidebar

Visual positioning:

![Islands region](images/islands-regions.webp)

## Interface

Notable methods:

- `IslandInterface::build()`: Build the renderable content of the island from state data. This renderable can be annotated by `HtmxEvents` to trigger HTTP requests
- Everything from `IslandEventSubscriberInterface`: each method (`onAttachToSlot()`, `onMove()`, ()`onUpdate()`...) is an HTMX event managed by the HTTP `ApiController`
- `PluginFormInterface::buildConfigurationForm`: to make the island plugin configurable in the [Display builder profile (config entity)](configuration.md)

> 🚧 2025-07-01: PluginFormInterface not implemented yet. [#3529067](https://www.drupal.org/project/display_builder/issues/3529067)

HTMX behavior will change according to `IslandInterface::build()` return value:

- `null`: the island is not altered
- `empty`: the island is emptied

### Attributes

- `id`: Plugin ID
- `label`: The human-readable name of the plugin.
- `description`: A brief description of the plugin.
- `type` : The island type from enumeration.
- `keyboard_shortcuts`: List of keyboard shortcuts as keypress => description.
- `icon`: Icon for this island. Used for View panels.

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
