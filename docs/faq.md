# Frequently Asked Questions

## Where are my displays stored?

Display builder is storing displays at the most "normal" place possible:

- [Entity View Display](entity-displays.md): in config entity's Third Party Settings, like Layout Builder do.
- [Entity View Display Overrides](entity-displays-overrides.md): in an UI Patterns content entity field.
- [Views](with-views.md): in a Display Extender plugin, so in the View config entity.
- [Page Layout](page-layout.md): in a dedicated config entity.

Everything at the right place.

## Why my SDC component doesn't work well with Display Builder?

Display Builder is not doing anything anything specific with your SDC. It is only an user interface upon UI Patterns 2.

UI Patterns 2 is not expecting you to change your SDC component in order to be compatible.

However, if your component has definitions or templating issues, it will be harder for UI Patterns 2.x to connect it to Drupal API

You can check your component with [sdc_devel](https://www.drupal.org/project/sdc_devel) which provides automatic audits.

A component without error must be compatible with UI Patterns 2.x and Display Builder.

## Why my preprocess hook is not triggered anymore?

Display Builder is avoiding as much as possible to interact with `ThemeManager::render()`:

- By design, Entity View Display integration is sometimes bypassing `node.html.twig`, `user.html.twig`, `media.html.twig`, `field.html.twig`...
- By design, Page Layout integration is sometimes bypassing `page.html.twig` and `region.html.twig`
- By design, Views integration is sometimes bypassing `views-view.html.twig`, `views-view-field.html.twig`...

So, your preprocess and suggestions related to those templates will not be executed.

## What is the relationship with UI Patterns and UI Suite?

This is a display building tool made by a team specialized in design systems and display building since 2017: [UI Suite](https://www.drupal.org/project/ui_suite) people.

This project is new but it is only a thin layer upon APIs we are building for many years, and which are already used, tested and loved by many: [UI Patterns](https://www.drupal.org/project/ui_patterns).

## Is it related to Canvas (Experience Builder)?

Like Canvas, this project is a next generation display building tool for Drupal.

However, the 2 projects are different. Display Builder is currently targeting a wider **horizontal** scope (more display building coverage):

|                             | Canvas                               | Display Builder               |
| --------------------------- | ------------------------------------ | ----------------------------- |
| Layout builder replacement  | ✅ Config & content overrides        | ✅ Config & content overrides |
| Page layout replacement     | ⚠️ Yes, but each region is a builder | ✅ Full page support          |
| View (displays) replacement | ❌ Out of scope                      | ✅                            |
| Entity form modes           | ❌ Out of scope                      | ⚠️ Planned                    |

But Canvas is currently targeting a deeper <strong>vertical</strong> scope (before and after display building):

|                     | Canvas                   | Display Builder                         |
| ------------------- | ------------------------ | --------------------------------------- |
| Content editing     | ✅ the main feature      | ⚠️ Planned                              |
| Component authoring | ✅ the "code components" | ❌ Out of scope, we promote SDC instead |

Both share more or less the same feature set:

|                         | Canvas              | Display Builder     |
| ----------------------- | ------------------- | ------------------- |
| Pattern presets         | ✅ as config entity | ✅ as config entity |
| History, undo, redo     | ✅                  | ✅                  |
| Real-time collaboration | ❌                  | ✅                  |

And they also differ by the technical and strategic choices. For example, Canvas is a complete ReactJS app, aside of Drupal, when Display Builder is just an usual Drupal module using HTMX.

So we are going in 2 different directions and our friendly competition will be only on the shared subset of our scopes. So, not such a big deal.

We hope both will be usable in a same project if this is needed by a team. Anyway, we are actively collaborating to provide same or compatible low level API and to improve Drupal Core together. So it is a win-win situation.

## Why am I encountering `TypeError: Drupal\canvas\PropShape\PropShape::componentPluginManager()`?

As of March 2026, Canvas and Display Builder can't be used in the same website because of those issues:

- On Canvas side: [ComponentPluginManager decorator should call decorated service instead of parent](https://www.drupal.org/project/canvas/issues/3552818)
- On UI Patterns (which is a dependency of Display Builder) side: [ComponentPluginManager decorator incorrectly calls parent instead of decorated service](https://www.drupal.org/project/ui_patterns/issues/3551586)

If one of the module is fixing the issue, it will be enough. If both modules are fixing the issue, it will be perfect.

## Comparison with Layout Builder

### What is the equivalent of [Layout Builder Restrictions](https://www.drupal.org/project/layout_builder_restrictions)?

`layout_builder_restrictions` provides a configurable UI for restricting blocks and layouts. Sites can allow all options from a certain provider, or restrict all options by provider, or specify individual allowed blocks & layouts.

`layout_builder_restrictions_by_role` allows restricting what roles can place what blocks or use what layout (so, what component).

This is doable with Display Builder by creating different Display builder profiles by role and [to configure](configuration.md) the _Components library_ and _Block library panels_ differently.

### What is the equivalent of [Layout Builder Lock](https://www.drupal.org/project/layout_builder_lock)?

Layout Builder Lock allows administrators to lock sections of a default layout so users can't perform certain actions when overriding the layout for an individual entity.

This feature is planned for [#3551232](https://www.drupal.org/i/3551232)

## Display Builder is acting weird, what can I do?

If you have issues while using Display Builder, you can mitigate them without losing any published data.

### Browser-side reset

We use `localStorage` that can change anytime, be sure to clear your local storage on each new install to start fresh.

- On Mozilla Firefox: `Privacy & Security` > `Cookies and Site Data` > Select the site > `Remove Selected` > `Save Changes`
- On Google Chrome: `Developer toolbar` > `Application` > `Local storage` > Select the site > `Clear`

### Server-side reset

Instance entities are volatile storages for the current, often unpublished, work on displays.

- Install and enable module `display_builder_dev_tools`
- Go to Structure > Display Builder > Instances
- `Delete` from the _Operations_ dropdown of each instance
