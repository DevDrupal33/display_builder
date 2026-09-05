# PHP Architecture

This document describes the core PHP design of Display Builder for contributors and module integrators. For the data formats that this architecture reads and writes, see [Internals](internals.md).

## Instance entity

`display_builder_instance` (`src/Entity/Instance.php`) is the heart of the module. It stores the working state of a builder session — the source tree, undo/redo history, and profile reference — and exposes the high-level API used by controllers.

Always type-hint against `Drupal\display_builder\InstanceInterface`.

The instance delegates all tree operations to `SourceTree` and provides:

- `attachToRoot()` / `attachToSlot()` / `moveToRoot()` / `moveToSlot()` / `setSource()` / `setThirdPartySettings()` / `remove()` — tree mutations, each recorded as a new present through `HistoryInterface::setNewPresent()`
- `getPast()` / `getFuture()` — the revision stack that `ApiController::undo()` and `redo()` step through
- `publish()` / `restore()` / `revert()` — `PublishableInterface`, the bridge to the permanent storage owned by the buildable plugin
- `getSources()` — the resolved tree as a flat array for debugging
- `getPathIndex()` — the `SourceTree` path index cache
- `getHash()` / `getPublishedHash()` — draft and published fingerprints; equal means nothing to publish

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

- `dispatchDisplayBuilderEvent(string $event_id, ...)` — builds the typed event, dispatches it, and collects every island's result into the out-of-band HTMX response
- `createEventWithEnabledIsland(string $event_id, ...)` — typed event factory using a `match()` expression; returns the correct typed subclass per event ID
- `getVisibleIslands()` — reads the `X-DB-Visible-Islands` request header, so islands the client reports as off screen are not rebuilt
- `saveSseData(string $event_id)` — records the change for the real-time collaboration stream

The instance itself is a route parameter (`{display_builder_instance}`), upcast by the entity param converter, never loaded by hand in a controller.

The API surface is split across several controllers rather than one:

| Controller | Responsibility |
|---|---|
| `ApiController` | attach to root/slot, get, reload island, update, third-party settings, undo, redo |
| `ApiActionsController` | paste, delete, save as preset, paste and delete styles |
| `ApiPublishingController` | publish, restore, revert |
| `ApiPreviewController` | component, block and preset library previews |
| `ApiSseController` | server-sent events for real-time collaboration |

**Never put business logic in controllers.** Mutations belong in `InstanceInterface` methods; side effects belong in event subscribers.

## Event system

After every tree mutation, the controller dispatches a Symfony event. The event then fans out to every enabled island plugin so each can update itself via HTMX out-of-band swaps.

### Event name = method name contract

Every constant in `DisplayBuilderEvents` is set to the camelCase island method name it maps to:

```php
const ON_PUBLISH = 'onPublish';
```

## Island plugin system

Islands are Drupal plugins (`src/Plugin/display_builder/Island/`) annotated with `#[Island(...)]`. All island layer code lives in `src/Island/`.

### Reload traits

Every island event is a method of the single `IslandEventSubscriberInterface` (11 methods). `IslandPluginBase` no-ops all of them, so an island overrides only what it answers. Three traits answer the common groups with a full reload through `reloadWithGlobalData()`, which the using island must provide:

| Trait | Covers |
|-------|--------|
| `IslandStructureReloadTrait` | `onAttachToRoot`, `onAttachToSlot`, `onMove`, `onUpdate`, `onDelete` |
| `IslandLifecycleReloadTrait` | `onHistoryChange`, `onRestore`, `onRevert` |
| `IslandReloadEventsTrait` | Both of the above — convenience aggregate |

Not covered by any trait, so an island that needs them overrides directly: `onActive`, `onPublish`, `onPresetSave`.

## DisplayBuildable integration pattern

External integrations (entity view, page layout, Views) implement `DisplayBuildableInterface` via `DisplayBuildablePluginBase`. This separates builder logic from storage backends.

When working in integration submodules, always follow this chain:

```
getInstanceId() → getInstance() → getSources() → publish()
```

Never access the instance entity storage directly in integration code.
