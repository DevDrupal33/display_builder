# Use Display Builder for entity view displays

As a replacement of Layout Builder and many modules from its ecosystem.

## Activate

You need `display_builder_entity_view` module.

Display builder can be activated for each display, in the "Display builder" fieldset:

![Activate display builder](images/entity-view-activate.webp)

## Use

Once yor Display builder selection is submitted, you will have access to twice the same link:

- The big "Display builder" button replacing the field formatter table
- An other button under the profile selector

![Activated](images/entity-view-activate-2.webp)

Slot sources specific to the Entity Display context will show up in the "Block Library" panel:

![Entity sources](images/entity-display-sources.webp)

Display Builder is saving every state in the memory, but is not auto-saving to the configuration.

You can manually publish the current state in the configuration or restore the state currently published in the configuration:

![State Buttons](images/state-buttons.webp)

## Use with Layout Builder

Both can be activated at the same time and they don't conflict while building the display:

![Entity View](images/entity-view-lb.webp)

However, only one of the tool will be used to render the content.
