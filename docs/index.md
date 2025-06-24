# Introduction

!!! warning "Development stage"

    <h2>The module is still in heavy development and is **not intended for Production** yet!  
    Follow us on slack [#display_builder](https://drupal.slack.com/archives/C092EUNPCRW)</h2>

A display building tool by the [UI Suite](https://www.drupal.org/project/ui_suite) team:

- **Design system native**: fully use your design system (components, style utilities, icons, themes/modes, CSS variables...) directly in Drupal without the burden of compatibility layers
- **Unified**: can be used instead of Layout Builder for entity view displays, Block Layout for page displays, and as a replacement of the Views' display building feature.
- **Modern**: A builder for the world of today, with powerful features (dynamic previews, pattern presets, real-time collaboration, deep integration with Drupal APIs...)

## Contributing

### How to Contribute

If you'd like to participate in Display Builder development, thank you!

First step is to join our slack [#display_builder](https://drupal.slack.com/archives/C092EUNPCRW)

Display Builder is in active development, codebase can change heavily, be
prepared for rebasing while helping us.

### Pull Requests

We accept only **pull requests**(PR), no patches.

!!! warning "PR and CI"
    <h2>Please ensure your PR is all green on ci before asking for review, or your PR will mostly be ignored!</h2>

Generally we like to see a PR that:

- Maintain the existing code style, ci pass is mandatory, if not review will mostly be ignored
- Are focused on a single change (i.e. avoid large refactoring or style adjustments in untouched code if not the primary goal of the pull request)
- Have Drupal commit message compatible with [conventional commits](https://www.conventionalcommits.org/en/v1.0.0/) as much as possible
- Have tests, if possible
- Don't decrease the current code coverage

## Installation

!!! note "Drupal compatibility"
    The **1.0.x** branch is for **Drupal 11.1**. No Drupal **10.x** support is planned yet.

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
See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules)
for further information.

## Libraries for local development

Display builder rely on [Shoelace component library](https://shoelace.style/getting-started/installation),
by default the library is loaded with CDN, but you can use local copies instead
in Display Builder settings (/admin/structure/display-builder).

**Local development installation**

Currently asset.packagist do not provide the last version of Shoelace, installation
with package manager is recommended.

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

## Maintainers

Current maintainers:

- Jean Valverde - [mogtofu33](https://www.drupal.org/u/mogtofu33)
- Mikael Meulle - [just_like_good_vibes](https://www.drupal.org/u/just_like_good_vibes)
- Pierre Dureau - [pdureau](https://www.drupal.org/u/pdureau)

Supporting organizations:

- [Beyris](https://www.drupal.org/beyris) - We are leading impactful open-source
projects and we are providing coding, training, audit and consulting.
