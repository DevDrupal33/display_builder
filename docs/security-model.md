# Security model

## Use Display Builder

To use a Display Builder instance, an user role needs at least 2 permissions:

- The specific permission to use the Display Builder profile associated to the instance
- The classic permission related to the display we are building. For example, `Administer display` for entity view displays, or `Administer views ` for view displays.

Those permissions are checked at 2 different levels:

- when accessing the Display Builder, by the controllers which are loading `ProfileViewBuilder`, through `ProfileAccessControlHandler`
- when using the Display Builder, by the API controllers, through `InstanceAccessControlHandler`

## Administer Display Builder

Display Builder UI sub-module add a few permissions:

- `Administer Display Builder profiles` for pages in `/admin/structure/display-builder`
- `Administer Display Builder presets` for page in `/admin/structure/display-builder/preset`
- `List Display Builder instances` for `/admin/structure/display-builder/instances` page
