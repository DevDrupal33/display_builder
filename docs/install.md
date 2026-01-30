# Installation

!!!warning "Drupal 11.3"
    Display builder targets Drupal **11.3**. No Drupal 10.x support is planned unless sponsored.

## Quick install new project

To simply test Display Builder with Bootstrap.

### Download and configure project

```shell
composer create-project drupal/recommended-project:11.3 display_builder_demo
cd display_builder_demo
composer config minimum-stability "alpha"
composer config --json extra.merge-plugin '{ "include": ["web/modules/*/display_builder/composer.json"] }'
composer config extra.enable-patching "true"
composer require cweagans/composer-patches:^1 wikimedia/composer-merge-plugin:^2 drupal/display_builder:^1 drupal/ui_suite_bootstrap:^5 drupal/ui_icons:^1
composer require drush/drush
```

### Run and install

Run and Install the website, for example with [DDEV](https://www.drupal.org/docs/getting-started/installing-drupal/install-drupal-using-ddev-for-local-development).

Recommended PHP 8.3, install Drupal in Standard profile:

```shell
ddev drush -y si standard
```

### Enable modules

Some minimum modules are required to properly use Display Builder with Bootstrap:

- Display Builder
- Display Builder for entity view
- Display Builder for page layout
- UI Patterns
- UI Patterns Library
- UI Patterns Field
- UI Patterns Field Formatters
- UI Styles

Enable from Extend page or with drush:

```shell
ddev drush -y en display_builder_entity_view display_builder_page_layout ui_styles
```

### Enable theme

Got to _Administration > Appearance_:

- **Install and set as default** the UI Suite Bootstrap theme.
- Uninstall Olivero theme

### Create your first Page Layout

Go to _Administration > Structure > Page Layouts_

- Add a page Layout
- Label: Default, Profile: Default
- Save and click the operation "Build display"

Once publish, your display will be used on all pages of the front of your site.

### Create your first Entity display

Go to _Administration > Structure > Content types_

- In the Article line, choose the operation "Manage display"
- Select Display builder Profile as "Default"
- Save and click "Build the display"

Once publish, your display will be used for all Articles.

Check this documentation for more insight and usage of Display Builder!

## Patches details

Display Builder require specific dependencies, it's recommended to ease this step using [Composer Merge Plugin](https://github.com/wikimedia/composer-merge-plugin) with this configuration in your main composer file:

```shell
composer require cweagans/composer-patches:^1 wikimedia/composer-merge-plugin:^2
composer config --json extra.merge-plugin '{ "include": ["web/modules/*/display_builder/composer.json"] }'
composer config extra.enable-patching "true"
```

## Local libraries

Display Builder rely on [Shoelace component library](https://shoelace.style/getting-started/installation),
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

Currently asset.packagist provide a version of Shoelace with Lit dependencies.

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

## Troubleshooting

### Browser-side reset

We use `localStorage` that can change anytime, be sure to clear your local storage on each new install to start fresh.

- On Mozilla Firefox: `Privacy & Security` > `Cookies and Site Data` > Select the site > `Remove Selected` > `Save Changes`
- On Google Chrome: `Developer toolbar` > `Application` > `Local storage` > Select the site > `Clear`

### Server-side reset

In case of a failing Display builder profile (config entity) or instance:

- Install and enable module `display_builder_dev_tools`
- Go to Structure > Display Builder > Devel
- `Delete` from the _Operations_ dropdown
