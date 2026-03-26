# Use Display Builder with Views

Drupal's Views module is both a query builder and a display builder:

- **query building** is done with filter, sort, contextual filters and relationships plugins. Display Builder is not dealing with them
- **display building** is done with style, pager, area, exposed form... plugins. Display Builder is not managing them directly but is interacting with them.

Display Builder is providing a nicer way of using, positioning & configuring View's display regions:

- Exposed form
- Attachment before & Attachment after
- Header & Footer areas
- Rows
- Pager & More

Visual representation:

![Views region](images/views-regions.webp)

## Activate

You need `display_builder_views` module.

On the view, Display Builder can be activated in "Other":

![Views activation 1](images/views-activation-1.webp)

You can pick the builder you want according to your permissions:

![Views activation 2](images/views-activation-2.webp)

## Use Display Builder

Once you have submitted your display builder selection, a link is available:

![Views activation 2](images/views-activation-3.webp)

We have access to Views related sources for slots in the _Blocks Library_ panel:

![Views sources](images/views-sources.webp)

View Title source is also available for string prop:

![Views title](images/views-title.webp)

> 🚧 2025-08-26: Previews are not working yet. See [#3542796](https://www.drupal.org/i/3542796)

> 🚧 2025-08-26: We are not able to configure the sources directly from Display Builder yet. See [#3533043](https://www.drupal.org/i/3533043)
