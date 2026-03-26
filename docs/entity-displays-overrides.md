# Entity view displays overrides with Display builder

## Activate

You need `display_builder_entity_view` module and `ui_patterns_field` sub-module form [UI Patterns 2](https://www.drupal.org/project/ui_patterns) project.

Contrary to Layout Builder, there is no "Allow each content item to have its layout customize" checkbox in the "Manage display" and no "magic" field added to the content bundle.

You can activate Content Overrides for each display:

![Before activate](images/entity-view-activate-2.webp)

On activation, you can pick the [Config Profile](configuration.md) the content editors will use:

![Activate 1](images/overrides-activate-1.webp)

A content field has been automatically created to store the overrides:

![Storage](images/overrides-field.webp)

The field can be changed later:

![Activate 2](images/overrides-activate-2.webp)

It is not possible to pick the same field in different displays.

## Use Display builder in the content

Any user with both the permission to edit the content and the one to use the display builder profile can override the display.

A mechanism similar to Layout Builder's overrides, but not limited to the default display.

If the user can override at least one display, a 'Display' tab id added in the content edit tabs:

![Tabs](images/overrides-tabs.webp)

If the user can override only one display, this tab is a direct link to this display. If the user can override many, a second row of tabs is visible:

![Sub tabs](images/overrides-tabs-2.webp)

The builder is a regular one with the same sources as Entity View Display plus some sources only available when editing a content:

![Builder](images/overrides-builder.webp)

The "Publish" button store the display in the content field. The "Restore" button load the display from the content field.
