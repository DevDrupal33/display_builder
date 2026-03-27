# Update

!!!warning "Update path"
    Until Display Builder is stable, update path is provided as **best effort**
    and cannot be guaranteed.

Update problems are mitigated by the fact that Display Builder stores its
configuration in Drupal configuration system and fields for entity overrides.
See [internals](./internals.md) for more details.

Currently a temporary working storage is leveraging Drupal [State API](https://www.drupal.org/docs/develop/drupal-apis/state-api/state-api-overview) to store Display work in progress and history.

!!!warning "Unpublished Display"
    Display Builder module update will **always** delete this working storage, all
    work-in-progress and history will be **permanently lost**!

## Updating Display Builder

- Ensure any Displays you are working on are published
- Export your Drupal configuration
- Backup your website
- Update Display Builder
- Run Drupal update, see [Updating Modules](https://www.drupal.org/docs/extending-drupal/updating-modules)

## Troubleshooting

If something goes wrong with the update, **restore your website** and:

- Export your Drupal configuration
- Uninstall Display Builder and all its sub-modules
- Update Display Builder module, see [Updating Modules](https://www.drupal.org/docs/extending-drupal/updating-modules)
- Import your configuration (this should install Display Builder)
