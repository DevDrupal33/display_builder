# Installation

⚠️ The **1.0.x** branch targets Drupal 11.1. No Drupal 10.x support planned for now.

## Patches

Display Builder could require patches, always check and include what's in [composer.json](https://git.drupalcode.org/project/display_builder/-/blob/1.0.x/composer.json).

It's recommended to ease this step using [Composer Merge Plugin](https://github.com/wikimedia/composer-merge-plugin) with this configuration in your main composer file:

```yaml
{
    # [...]
    "config": {
        "allow-plugins": {
            # [...]
            "wikimedia/composer-merge-plugin": true
        }
    },
    "extra": {
        # [...]
        "merge-plugin": {
            "include": [
                "web/modules/*/display_builder/composer.json"
            ]
        },
    }
}
```

So your installation will be sync with Display Builder specific patches.

## Configuration steps

Before enabling `display_builder`, you **MUST**:

- Disable JavaScript files aggregation to avoid issues with the _[Entity] ➜ [Field]_ context switcher in entity view displays
- Activate your component-based theme as the default front theme (to allow some temporary demo fixtures to be loaded)

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

Display Builder rely on [Shoelace component library](https://shoelace.style/getting-started/installation),
by default the library is loaded with CDN, but you can use local copies instead

in Display builder profiles (/admin/structure/display-builder).

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

In case of a failing Display builder profile (config entity) or instance:

- Enable module `display_builder_devel`
- Go to Structure > Display Builder > Devel
- `Delete` from the _Operations_ dropdown
