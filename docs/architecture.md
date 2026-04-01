# PHP Architecture

This document describes the core PHP design of Display Builder for contributors and module integrators. For the data formats that this architecture reads and writes, see [Internals](internals.md).

## Instance entity

`display_builder_instance` (`src/Entity/Instance.php`) is the heart of the module. It stores the working state of a builder session — the source tree, undo/redo history, and profile reference — and exposes the high-level API used by controllers.

Always type-hint against `Drupal\display_builder\InstanceInterface`.

The instance delegates all tree operations to `SourceTree` and provides:

- `addToRoot()` / `addToSlot()` / `move()` / `update()` / `delete()` — tree mutations (each pushes to history)
- `undo()` / `redo()` / `restore()` — history traversal
- `getCurrentState()` — the resolved tree as a flat array for debugging
- `getPathIndex()` — the `SourceTree` path index cache

## SourceTree — normalized tree engine

`src/SourceTree.php` stores the tree as three flat maps:

| Map | Contents |
|-----|----------|
| `$nodes` | Raw node data keyed by node ID |
| `$structure` | Parent/slot relationships keyed by node ID |
| `$root` | Ordered list of root-level node IDs |

This enables O(1) mutations regardless of nesting depth. To locate nodes efficiently, `SourceTree` maintains a `$pathIndex` cache built via `buildPathIndex()`.

**Critical rules:**

- When moving nodes, always **remove first**, then re-calculate the target parent's path from the updated tree before attaching. Array index shifts after removal make the pre-removal path stale.
- `remove()` re-indexes parent arrays via `array_values` to keep paths consistent.
- `moveToSlot()` includes an `isDescendant()` guard that prevents circular / ancestor moves.

## API-first controllers

The UI is powered by a RESTful API defined in `display_builder.routing.yml`. Controllers are thin entry points — they load the instance, delegate business logic to the instance entity, dispatch an event, and return an HTML partial for HTMX to swap.

`ApiControllerBase` provides the shared plumbing:

- `loadInstance(string $id): InstanceInterface`
- `createEventWithEnabledIsland(string $event_id, ...)` — typed event factory using a `match()` expression; returns the correct typed subclass per event ID
- `buildOobResponse(DisplayBuilderEvent)` — collects island results and builds the out-of-band HTMX response

**Never put business logic in controllers.** Mutations belong in `InstanceInterface` methods; side effects belong in event subscribers.

## Event system

After every tree mutation, the controller dispatches a Symfony event. The event then fans out to every enabled island plugin so each can update itself via HTMX out-of-band swaps.

### Event name = method name contract

Every constant in `DisplayBuilderEvents` is set to the camelCase island method name it maps to:

```php
const ON_PUBLISH = 'onPublish';
```

`IslandFanOutTrait::dispatchToIslands()` uses this convention to call the right island method generically. Any new event **must** follow this rule.

### Typed event classes

| Class | Extra fields | Used for |
|-------|-------------|----------|
| `DisplayBuilderEvent` | — (base) | ON_HISTORY_CHANGE, ON_RESTORE, ON_REVERT |
| `DisplayBuilderNodeEvent` | `string $nodeId` | ON_ATTACH_TO_ROOT, ON_MOVE, ON_UPDATE |
| `DisplayBuilderSlotEvent` | `string $nodeId`, `string $parentId` | ON_ATTACH_TO_SLOT |
| `DisplayBuilderDeleteEvent` | `?string $parentId` | ON_DELETE |
| `DisplayBuilderDataEvent` | `array $data` | ON_ACTIVE, ON_PUBLISH |

Always type-hint subscriber handlers against the specific subclass, not the base `DisplayBuilderEvent`.

### Fan-out: IslandFanOutTrait

`src/Island/IslandFanOutTrait.php` contains the canonical fan-out algorithm. Any event subscriber that needs to forward events to islands should use this trait (the using class must provide `IslandPluginManagerInterface $islandManager`).

- `dispatchToIslands(event, method, parameters)` — for core events listed in `METHOD_INTERFACE_MAP`
- `dispatchCustomEventToIslands(event, method, island_interface, parameters)` — for submodule-defined events; accepts an explicit interface so the instanceof check resolves correctly

`METHOD_INTERFACE_MAP` maps each method name to the sub-interface that declares it. Islands that do not implement the required interface are silently skipped — no changes needed in existing islands when new events are added.

See [Adding a custom island event](island-plugins.md#adding-a-custom-island-event) for a complete walkthrough.

## Island plugin system

Islands are Drupal plugins (`src/Plugin/display_builder/Island/`) annotated with `#[Island(...)]`. All island layer code lives in `src/Island/`.

### Event sub-interfaces

Island event methods are split into four focused interfaces. Islands only implement what they need:

| Interface | Methods |
|-----------|---------|
| `IslandStructureEventsInterface` | `onAttachToRoot`, `onAttachToSlot`, `onMove`, `onUpdate`, `onDelete` |
| `IslandLifecycleEventsInterface` | `onHistoryChange`, `onRestore`, `onRevert` |
| `IslandSaveEventsInterface` | `onPublish`, `onPresetSave` |
| `IslandActiveEventInterface` | `onActive` |

`IslandEventSubscriberInterface` is an empty aggregate that extends all four — existing code type-hinted against it continues to work unchanged.

`IslandPluginBase` implements the full aggregate with no-op defaults for every method.

### Reload traits

| Trait | Covers |
|-------|--------|
| `IslandStructureReloadTrait` | `IslandStructureEventsInterface` (5 methods → `reloadWithGlobalData`) |
| `IslandLifecycleReloadTrait` | `IslandLifecycleEventsInterface` (3 methods → `reloadWithGlobalData`) |
| `IslandReloadEventsTrait` | Both of the above — convenience aggregate |

## DisplayBuildable integration pattern

External integrations (entity view, page layout, Views) implement `DisplayBuildableInterface` via `DisplayBuildablePluginBase`. This separates builder logic from storage backends.

When working in integration submodules, always follow this chain:

```
getInstanceId() → getInstance() → getSources() → saveSources()
```

Never access the instance entity storage directly in integration code.
