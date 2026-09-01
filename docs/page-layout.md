# Use Display Builder for Page Layout

As a replacement of Block Layout (`/admin/structure/block`)

> A page is assembled from several levels at once: a page layout places the
> page's main content. What fills it is a display: an entity's view display,
> or a Views display. A display can itself place another display, by
> rendering a reference field with a view mode.
>
> See [How displays nest](how-displays-nest.md).

## Activate

You need `display_builder_page_layout` module.

There is no page layout activated by default. You need to create your owns in Administration > Structure > Page layouts (`/admin/structure/page-layout`):

![Page layout overview](images/page-layout-overview-1.webp)

## Create and edit

Adding a page layout requires the following steps:

1. Set the Label
2. Pick your Display Builder profile
3. Set your conditions

![Add page layout](images/page-layout-add.webp)

Condition plugins is the heart of the form:

- Drupal Core already provides a few plugins. **Pages** is the most commonly used. **Language** is visible only if at least two languages are activated in the site.
- You can add more conditions by activating or developing Drupal modules.
- Most condition plugin utilize contexts. The context will be the one of the page visitors will load.
- All conditions must be met for a page layout to load.

## Use Display Builder

You will be able to build the display once the Page Layout is created.

From the overview page:

![Page layout overview](images/page-layout-overview-2.webp)

Or from the edit page:

![Edit page layout](images/page-layout-edit.webp)

The Display Builder instance is a normal one with some data preloaded (but not already saved) and some sources specific to the *Page* context:

- `[Page] title`
- `[Page] Main content`
- `Primary admin actions`
- `Tabs`

![Display Builder for Page layout](images/page-layout-builder.webp)

Other modules can provide Source plugins, and [you can add your owns](island-plugins.md).

## Manage

All _Page layouts_ are manageable from the overview page:

![Page layout overview](images/page-layout-overview-3.webp)

_Page layouts_ are draggable and orderable. Order is important. Don't forget to save.

From top to bottom, checking _Page layouts_ one by one, the first one which is
both:

- enabled with non-empty Display Builder
- applicable according to all its conditions in the context of the page

will be loaded for the page, as a replacement of both:

- _Block Layout_ admin UI
- the page regions defined in the theme (with the related `page.html.twig`
  template)

It is better to keep the most specific ones at the top, and the more generic at
the bottom.

!!!tip
    Order matters! Position your most specific condition checks at the top, since
    Display Builder will use the first matching page layout. Generic layouts should
    be at the bottom as fallbacks.

If no Page layout matches, _Block Layout_ still manage the page.

Disabling a page layout is one way to stop it matching: the pages it used to cover fall back to the next matching layout below it, or to _Block Layout_ and the theme's own regions if none do. Nothing about the previously overridden pages is lost by disabling; it only stops taking them over.

You can build a page layout entirely while it is disabled, then enable it once it is ready: only viewing a page is gated by the enabled status, editing and publishing the display are not. This is a normal, supported way to prepare a layout without exposing an unfinished one to visitors.
