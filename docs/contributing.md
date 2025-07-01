# Contributing

## How to contribute

If you'd like to participate in Display Builder development, thank you!

First step is to join our slack [#display_builder](https://drupal.slack.com/archives/C092EUNPCRW)

Display Builder is in active development, codebase can change heavily, be prepared for rebasing while helping us.

### Pull requests

We accept only pull requests (PR), no patches, with the following expectations:

- Maintain the existing code style, CI pass is mandatory. ⚠️ Please ensure your PR is all green on CI before asking for review
- Are focused on a single change (i.e. avoid large refactoring or style adjustments in untouched code if not the primary goal of the pull request) from a single Drupal issue
- Have tests if possible
- Don't decrease the current code coverage

Commit message structure **must** have the issue ID and **can** have the contribution credit:

- ✅ Issue #3529070 by pdureau, mogtofu33: Use PluginSettingsInterface::settingsSummary()
- ✅ Issue #3529070: Use PluginSettingsInterface::settingsSummary()
- ❌ Use PluginSettingsInterface::settingsSummary()

Naming rules:

- "Display Builder" with upper-case "B" for the module
- "Display builder" with lower-case "b" for the config entity or for a plugin

## Installation

⚠️ The **1.0.x** branch targets Drupal 11.1. No Drupal 10.x support planned for now.

Before enabling `display_builder`, you may need to:

- Disable JavaScript files aggregation to avoid issues with the _[Entity] ➜ [Field]_ context switcher in entity view displays.
- Activate your component-based theme as the default front theme (to allow some temporary demo fixtures to be loaded)
- Add some patches from [composer.json](https://git.drupalcode.org/project/display_builder/-/blob/1.0.x/composer.json) in your project's composer.json

With command-line:

```shell
drush -y config-set system.performance js.preprocess 0
drush theme:enable my_theme
drush -y config-set system.theme default my_theme
drush -y en display_builder
```

Install as you would normally install a contributed Drupal module.  
See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules) for further information.

## Libraries for local development

Display builder rely on [Shoelace component library](https://shoelace.style/getting-started/installation),
by default the library is loaded with CDN, but you can use local copies instead
in Display Builder settings (/admin/structure/display-builder).

### Local development installation

Currently asset.packagist do not provide the last version of Shoelace, installation with package manager is recommended.

From your installation libraries folder (`web/libraries` or `app/libraries`):

```shell
mkdir -p shoelace
cd shoelace
npm init -y
npm install @shoelace-style/shoelace
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
