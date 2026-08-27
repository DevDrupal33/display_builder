# Single Directory Components (SDC)

A component is a part of a web page that is modular and reusable, with a specific UI purpose. Components are made up of HTML, CSS, and JavaScript code. Examples of components include navigation menus, forms, sliders, and buttons.

[Single-Directory Components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/) (often abbreviated as SDC) are Drupal Core’s implementation of components. Within SDC, all files necessary to render the component are grouped together in a single directory (hence the name).

While other slot sources are related to the Drupal application state, SDC are context agnostic and owned by the theme as part of a design implementation.

## Metadata & library

Every SDC is a plugin with metadata:

```
name: Card
description: "Group and display content in an easily readable way."
group: "Data display"
status: experimental
noUi: false
```

Display Builder is leveraging those metadata in the Component Library panel:

- `name` as the label
- `status` ("experimental", "stable", "deprecated", "obsolete"): exposed as a filter in the island configuration
- `group` to group the components in the library
- `noUi`: exposed as a filter in the island configuration

## Slots

```
slots:
  image:
    title: Image
  title:
    title: Title
    description: "Card title. Plain text."
  actions:
    title: Actions
    maxItems: 2
```

Display Builder is using:

- `title`: as the slot label in View panels
- `maxItems` to prevent adding a source to a slot when 'full'

## Props

```
props:
  type: object
  properties:
    heading_level:
      title: "Heading level"
      type: integer
      enum: [2, 3, 4]
    image_bottom:
      title: "Image bottom"
      description: "Do you want to display image at bottom of the card?"
      type: boolean
```

Props are displayed as a form in the Contextual Panel.
