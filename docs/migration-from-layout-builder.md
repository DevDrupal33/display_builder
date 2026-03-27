# Migration from Layout Builder

In Display Builder Entity View sub-module.

## Migration of the entity view display config entity

This migration can be triggered in two different ways:

- When the Display Builder is initialized for the first time and Layout Builder is already activated. If Layout Builder is not used for the display, it will migrate from "Manage Display" data instead.
- Each time the Layout Builder is saved.

Each migration is a full migration, overriding the current Display Builder data, and is creating a new step in Display Builder logs:

![Migration logs](images/migration-logs.webp)

So, it is possible to undo each of them. We can have both tool installed at the same time and still using Layout Builder and keep testing Display Builder before doing the switch:

![Trigger initial migration](images/migration-activate.webp)

## Migration of the override content field

When an Display Builder override is initialized, there are 2 possible sources of data for initial import.

The import priority is not the same as the display priority:

|                 | For display              | For import in DB override                                                     |
| --------------- | ------------------------ | ----------------------------------------------------------------------------- |
| Higher priority | Display Builder override |
| P2              | Display Builder config   | Layout Builder override (available only for default display)                  |
| P3              | Layout Builder override  | Display Builder config                                                        |
| Lower priority  | Layout Builder config    | (Not applicable) (🚧 [but this may change](https://www.drupal.org/i/3540048)) |

> 🚧 2026-02-09: for display, what about Field settings default value?

## Migration process

Layout Builder is made of sections (a flat list of layout plugins, one layout per section) and components (a flat list of block plugins, inside each layout region).

Layout builder sections:

| Section's layout plugins    | Migrated as       |
| --------------------------- | ----------------- |
| SDC component (UI Patterns) | ✅ SDC component  |
| Other layouts               | ✅ Layout plugins |

| Section's other data | Migrated as              |
| -------------------- | ------------------------ |
| UI Styles data       |  ✅ Third party settings |

Layout builder components:

| Component's block plugin     | Migrated as                                            |
| ---------------------------- | ------------------------------------------------------ |
|  SDC component (UI Patterns) | ✅ SDC component.                                      |
| Field                        | ✅ Field                                               |
| Extra field                  | ❌ No proper conversion, only a placeholder is placed. |
| Other blocks                 | ✅ Imported as they are configured.                    |

| Component's other data | Migrated as                                            |
| ---------------------- | ------------------------------------------------------ |
| Third party settings   | ✅ Third party settings.                               |
| Block title            |  ❌ Not imported because no `block.html.twig` anymore. |

### ⚠️ Note about wrapper templates

Display Builder rendering does not load `block.html.twig` and `field.html.twig`, so it does not execute `hook_preprocess_block` and `hook_preprocess_field`.

So, the modules adding third-party settings and executing those hooks will still have the form displayed in the contextual panel, but the alteration of the renderable will not be executed.

Examples of such modules:

- [Fences](https://www.drupal.org/project/fences)
- [Field Formatter Range](https://www.drupal.org/project/field_formatter_range)

> 🚧 2025-08-26: This may change. See: [#3534619](https://www.drupal.org/i/3534619)
