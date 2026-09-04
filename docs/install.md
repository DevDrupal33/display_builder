# Installation

!!!warning "Drupal 11.4+"
    Display Builder targets Drupal **11.4+**. No Drupal 10.x support is planned unless sponsored.

Like any other Drupal module, it is recommended to use composer:

```shell
composer require drupal/display_builder
```

Display Builder is split in sub-modules, one for each Drupal Core's display buildable:

- [Entity view](entity-displays.md) and [entity view overrides](entity-displays-overrides.md) (`display_builder_entity_view`)
- [Page layout](page-layout.md) (`display_builder_page_layout`)
- [Views](with-views.md) (`display_builder_views`)

They can be activated from `Administration > Extend` (`/admin/modules`) or with Drush:

```shell
drush -y en display_builder_entity_view display_builder_page_layout display_builder_views
```

You can also install Display Builder UI to [configure Display Builder](configuration.md):

```shell
drush -y en display_builder_ui
```

With the module installed, follow [Build your first display](getting-started.md)
to build a display end to end.

## Recommended modules

Display Builder is automatically activating its dependencies:

- UI Patterns: the *engine* of Display Builder
- UI Patterns Field: for the [entity overrides](entity-displays-overrides.md) storage
- UI Patterns Field Formatters: to format each field item in entity displays

We are also recommending:

- UI Patterns Library (from `ui_patterns` module): provides [a nice component library](https://project.pages.drupalcode.org/ui_patterns/2-authors/1-stories-and-library/)
- [UI Styles](https://www.drupal.org/project/ui_styles): to use the **Styles** contextual panel
- [UI Skins](https://www.drupal.org/project/ui_skins): to use the **Design Tokens** contextual panel

## Patches

Display Builder may require specific patches for its dependencies, it's recommended to ease this step using [Composer Merge Plugin](https://github.com/wikimedia/composer-merge-plugin) with this configuration in your main composer file:

```shell
composer require cweagans/composer-patches:^2 wikimedia/composer-merge-plugin:^2
composer config --json extra.merge-plugin '{ "include": ["web/modules/*/display_builder/composer.json"], "merge-extra": true }'
composer config extra.enable-patching "true"
```

## Local libraries

Display Builder relies on [Shoelace component library](https://shoelace.style/getting-started/installation),
and HTMX [sse extension](https://htmx.org/extensions/sse/).

By default libraries are loaded with CDN, but you can switch to local copies with drush:

```shell
drush state:set display_builder.asset_libraries_local true
drush cache:rebuild
```

And switch back to CDN mode with drush:

```shell
drush state:delete display_builder.asset_libraries_local
drush cache:rebuild
```

Currently asset.packagist provides a version of Shoelace with Lit dependencies.

Installation with package manager is recommended.

From your installation libraries folder (`web/libraries` or `app/libraries`):

```shell
mkdir -p shoelace
cd shoelace
npm init -y
npm install @shoelace-style/shoelace
```

```shell
cd web/libraries
mkdir -p htmx-ext-sse
cd htmx-ext-sse
npm init -y
npm install htmx-ext-sse
```
