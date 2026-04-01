# Extend Display Builder with island plugins

> The general idea of an "Islands" architecture is deceptively simple: render HTML pages on the server, and inject placeholders or slots around highly dynamic regions […] that can then be "hydrated" on the client into small self-contained widgets, reusing their server-rendered initial HTML.

[Astro documentation](https://docs.astro.build/en/concepts/islands/)

Display Builder extensively uses HTMX's [out-of-band swapping](https://htmx.org/attributes/hx-swap-oob/) to allow an event triggered from an island to also update other islands.

There are 5 type of islands:

- `View` panels: They are displayed tabbed in the center of the toolbar, or as buttons in the start of the toolbar
- `Button`s: they are displayed as buttons in the end of the toolbar
- `Library` panels: They are displayed tabbed into the Library View panel
- `Menu` items: they are displayed in the contextual menu triggered with right-click
- `Contextual` panels: They are displayed tabbed into the contextual sidebar

Visual positioning:

![Islands region](images/islands-regions.webp)

## Interface

Notable methods:

- `IslandInterface::build()`: Build the renderable content of the island from state data. This renderable can be annotated by `HtmxEvents` to trigger HTTP requests
- `PluginFormInterface::buildConfigurationForm()`: to make the island plugin configurable in the [Display Builder profile (config entity)](configuration.md)

HTMX behavior will change according to `IslandInterface::build()` return value:

- `null`: the island is not altered
- `empty`: the island is emptied

### Attributes

- `id`: Plugin ID
- `label`: The human-readable name of the plugin.
- `description`: A brief description of the plugin.
- `type` : The island type from enumeration.
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

### Keyboard support

We provide a small utility to map buttons to keyboard shortcuts to ease actions in the Display Builder.

If your Island provide a button you should use our method `\Drupal\display_builder\RenderableBuilderTrait::buildButton()` to generate your button.
This method allow a keyboard mapping as parameter. Check the class for more info.

If your island is of type `IslandType::View`, implement the `keyboardShortcuts()` method.

_Note_: There is no control on duplicate, please ensure your shortcut is not already used.

## Events

Every state-changing operation dispatches a Symfony event. After the primary handler runs, Display Builder fans out the same event to all enabled island plugins so each can update itself out-of-band via HTMX.

The fan-out is implemented in `IslandFanOutTrait` and driven by a simple contract: the value of each `DisplayBuilderEvents` constant equals the island method name to call (e.g. `ON_PUBLISH = 'onPublish'`).

### Event sub-interfaces

Island event methods are grouped into four focused interfaces. Your island only needs to implement the interfaces relevant to it — the fan-out safely skips islands that do not implement the required interface.

#### `IslandStructureEventsInterface` — tree mutations

| Method | Triggered when |
|---|---|
| `onAttachToRoot(instance, node_id)` | A node is added at root level |
| `onAttachToSlot(instance, node_id, parent_id)` | A node is added into a component slot |
| `onMove(instance, node_id)` | An existing node is moved |
| `onUpdate(instance, node_id)` | A node's source configuration is updated |
| `onDelete(instance, parent_id)` | A node is removed |

#### `IslandLifecycleEventsInterface` — state lifecycle

| Method | Triggered when |
|---|---|
| `onHistoryChange(instance)` | The history pointer changes (undo / redo) |
| `onRestore(instance)` | The builder is restored to its last published state |
| `onRevert(instance)` | An entity view override is reverted to the base display config |

#### `IslandSaveEventsInterface` — save operations

| Method | Triggered when |
|---|---|
| `onPublish(instance)` | The builder state is published to permanent storage |
| `onPresetSave(instance)` | A node is saved as a reusable preset |

#### `IslandActiveEventInterface` — focus

| Method | Triggered when |
|---|---|
| `onActive(instance, data)` | A node becomes the focused node in the UI |

### Aggregate interface

`IslandEventSubscriberInterface` extends all four sub-interfaces. `IslandPluginBase` implements the full aggregate with no-op defaults for every method, so concrete islands only need to override what they care about.

### Reload helpers

Use these traits when your island should fully re-render itself in response to events:

| Trait | Covers |
|---|---|
| `IslandStructureReloadTrait` | `IslandStructureEventsInterface` (5 methods) |
| `IslandLifecycleReloadTrait` | `IslandLifecycleEventsInterface` (3 methods) |
| `IslandReloadEventsTrait` | Both of the above — convenience aggregate for existing islands |

All three delegate to `reloadWithGlobalData()`, which `IslandPluginBase` provides.

Example — an island that reloads on any tree mutation but has custom publish logic:

```php
use Drupal\display_builder\Island\IslandStructureReloadTrait;
use Drupal\display_builder\Island\IslandLifecycleReloadTrait;
use Drupal\display_builder\Island\IslandSaveEventsInterface;

class MyStatsPanel extends IslandPluginBase implements IslandSaveEventsInterface {

  use IslandStructureReloadTrait;
  use IslandLifecycleReloadTrait;

  public function onPublish(InstanceInterface $instance): array {
    // Custom logic after publish.
    return $this->reloadWithGlobalData($instance);
  }

  public function onPresetSave(InstanceInterface $instance): array {
    return [];
  }

}
```

## Adding a custom island event

Sometimes a submodule needs its own events that go beyond the core set. The steps below walk through adding a hypothetical `onFoo` event.

### 1. Define the island capability interface

Create an interface for the new event group in your submodule:

```php
// modules/my_submodule/src/Island/IslandFooEventInterface.php

namespace Drupal\my_submodule\Island;

use Drupal\display_builder\InstanceInterface;

interface IslandFooEventInterface {

  public function onFoo(InstanceInterface $instance, string $extra): array;

}
```

### 2. Define the Symfony event class

Create a typed event class that carries the data the handler needs:

```php
// modules/my_submodule/src/Event/FooEvent.php

namespace Drupal\my_submodule\Event;

use Drupal\display_builder\Event\DisplayBuilderEvent;

final class FooEvent extends DisplayBuilderEvent {

  public function __construct(
    // Required base parameters.
    \Drupal\display_builder\InstanceInterface $instance,
    array $island_configuration,
    array $island_enabled,
    ?string $current_island_id,
    // Extra data specific to this event.
    public readonly string $extra,
  ) {
    parent::__construct($instance, $island_configuration, $island_enabled, $current_island_id);
  }

}
```

### 3. Define the event constant

```php
// modules/my_submodule/src/Event/MySubmoduleEvents.php

namespace Drupal\my_submodule\Event;

final class MySubmoduleEvents {

  // The constant value MUST equal the method name on IslandFooEventInterface.
  const ON_FOO = 'onFoo';

}
```

### 4. Dispatch the event in your controller

In your controller, create and dispatch the event, then collect island responses:

```php
use Drupal\display_builder\Controller\ApiControllerBase;
use Drupal\my_submodule\Event\FooEvent;
use Drupal\my_submodule\Event\MySubmoduleEvents;

class MyController extends ApiControllerBase {

  public function foo(Request $request, string $instance_id): Response {
    $instance = $this->loadInstance($instance_id);

    $event = new FooEvent(
      $instance,
      $this->getIslandConfiguration($instance),
      $this->getEnabledIslands($instance),
      $this->islandId($request),
      extra: $request->query->get('extra', ''),
    );

    $this->eventDispatcher->dispatch($event, MySubmoduleEvents::ON_FOO);

    return $this->buildOobResponse($event);
  }

}
```

### 5. Subscribe and fan out to islands

Create a Symfony event subscriber that fans the event out to qualifying islands using `dispatchCustomEventToIslands()`:

```php
// modules/my_submodule/src/EventSubscriber/MyEventsSubscriber.php

namespace Drupal\my_submodule\EventSubscriber;

use Drupal\display_builder\Island\IslandFanOutTrait;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\my_submodule\Event\FooEvent;
use Drupal\my_submodule\Event\MySubmoduleEvents;
use Drupal\my_submodule\Island\IslandFooEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MyEventsSubscriber implements EventSubscriberInterface {

  use IslandFanOutTrait;

  public function __construct(
    protected readonly IslandPluginManagerInterface $islandManager,
  ) {}

  public static function getSubscribedEvents(): array {
    return [MySubmoduleEvents::ON_FOO => 'onFoo'];
  }

  public function onFoo(FooEvent $event): void {
    $this->dispatchCustomEventToIslands(
      $event,
      MySubmoduleEvents::ON_FOO,  // method name
      IslandFooEventInterface::class,  // only islands implementing this interface are invoked
      [$event->extra],  // extra parameters passed after $instance
    );
  }

}
```

Register the subscriber in `my_submodule.services.yml`:

```yaml
services:
  my_submodule.events_subscriber:
    class: Drupal\my_submodule\EventSubscriber\MyEventsSubscriber
    arguments: ['@plugin.manager.db_island']
    tags:
      - { name: event_subscriber }
```

### 6. Implement the interface in your island

```php
use Drupal\display_builder\IslandPluginBase;
use Drupal\my_submodule\Island\IslandFooEventInterface;

class MyIsland extends IslandPluginBase implements IslandFooEventInterface {

  public function onFoo(InstanceInterface $instance, string $extra): array {
    // React to the custom event.
    return $this->reloadWithGlobalData($instance);
  }

}
```

Islands that do not implement `IslandFooEventInterface` are silently skipped by the fan-out — no changes needed for existing islands.
