# Display Builder

A display building tool by the [UI Suite](https://www.drupal.org/project/ui_suite) team:

- **Design system native**: fully use your design system (components, style utilities, icons, themes/modes, CSS variables...) directly in Drupal without the burden of compatibility layers
- **Unified**: can be used instead of Layout Builder for entity view displays, Block Layout for page displays, and as a replacement of the Views' display building feature.
- **Modern**: A builder for the world of today, with powerful features (dynamic previews, pattern presets, real-time collaboration, deep integration with Drupal APIs...)

## Installation

The module is still in heavy development. So, before enabling `display_builder`, you may need to:

- Disable JavaScript files aggregation to avoid issues with the _[Entity] ➜ [Field]_ context switcher in entity view displays.
- Activate your component-based theme as the default front theme (to allow some temporary demo fixtures to be loaded)
- Add some patches from [composer.json](https://git.drupalcode.org/project/display_builder/-/blob/1.0.x/composer.json) in your project's composer.json

With command-line:

```
$ drush -y config-set system.performance js.preprocess 0
$ drush theme:enable my_theme
$ drush -y config-set system.theme default my_theme
$ drush -y en display_builder
```

### Local assets

By default, the asset libraries are using CDN, but you can use local copies instead by picking them from [display_builder.libraries.yml](https://git.drupalcode.org/project/display_builder/-/blob/1.0.x/display_builder.libraries.yml) and execute:

```
$ npm install
```

## Troubleshooting

### Browser-side reset

We use `localStorage` that can change anytime, be sure to clear your local storage on each new install to start fresh.

- On Mozilla Firefox: `Privacy & Security` > `Cookies and Site Data` > Select the site > `Remove Selected` > `Save Changes`
- On Google Chrome: `Developer toolbar` > `Application` > `Local storage` > Select the site > `Clear`

### Server-side reset

In case of a failing display builder configuration or instance:

- Enable module `display_builder_devel`
- Go to Structure > Display Builder > Devel
- `Delete` from the _Operations_ dropdown
