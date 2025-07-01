# Use Display Builder for entity view displays

As a replacement of Layout Builder and many modules from its ecosystem.

## Activate

You need `display_builder_entity_view` module.

> 🚧 2025-07-01: This "conflict" between Layout Builder and display Builder will be solved. [#3529127](https://www.drupal.org/project/display_builder/issues/3529127)

TODO

![](images/entity-view-activate.webp)

> 🚧 2025-07-01: The selector of display builders is not ready.

## Use

Slot sources specific to the Entity Display context will show up in the "Block Library" panel:

![](images/entity-display-sources.webp)

> 🚧 2025-07-01: "[Entity] ➜ [Field]" will be flatten. [#3529260](https://www.drupal.org/project/display_builder/issues/3529260)

Display Builder is saving every state in the memory, but is not auto-saving to the configuration. You can manually save the current state in the configuration or restore the state currently saved in the configuration:

![](images/state-buttons.webp)

## Under the hood

Display Builder data is stored as a third party settings with those properties:

- `display_builder`: the display builder config entity in use last time the config was saved
- `sources`: a UI Patterns 2 sources tree

> 🚧 2025-07-01: Property are not set yet [#3534215](https://www.drupal.org/project/display_builder/issues/3534215)

Example:

```yaml
id: node.article.default
targetEntityType: node
bundle: article
mode: default
content: {}
hidden: {}
third_party_settings:
  display_builder:
    enabled: true
    display_builder: default
    sources: [...]
```
