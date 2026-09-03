# Use Display Builder with Views

Drupal's Views module combines two distinct roles: query building and display
building. Display Builder focuses on the display building aspect, providing a
more intuitive interface for organizing and configuring the view's display
regions.

> A page is assembled from several levels at once: a page layout places the
> page's main content. What fills it is a display: an entity's view display,
> or a Views display. A display can itself place another display, by
> rendering a reference field with a view mode.
>
> See [How displays nest](how-displays-nest.md).

## Overview

Display Builder provides a nicer way of using, positioning & configuring View's
display regions:

- Exposed form
- Attachment before & Attachment after
- Header & Footer areas
- Rows
- Pager & More

Visual representation:

![Views region](images/views-regions.webp)

## Activate

You need `display_builder_views` module.

On the view, Display Builder can be activated in **Other**:

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

### Configure a view source

Sources backed by a view option configure in place, in the _Config_ panel: the
pager, the exposed form, the rows (the style plugin) and the more link show the
view's own options form. Saving goes through the view display, so a display
inheriting a section from _Default_ updates _Default_ rather than growing an
override, exactly as the Views UI does.

Header, footer, the two attachment sources and the feed icons still point at
the Views UI: they are either a list of area handlers or computed from another
display.

!!!warning
    A view option is stored on the view, not in the builder history: undo
    restores the layout, not the pager you just changed.

    While the view is open in the Views UI with unsaved changes, the builder
    refuses to save its options. Save or cancel them there first.

!!!note
    Header and footer areas, and picking a different plugin (a different
    pager type, for example), still need the Views UI. See the related issue
    for updates on this feature.

> ✅ 2026-09-03: A view region that rendered nothing (header, footer, an
> attachment...) now says that is up to the view, not to this node -
> distinct from the generic "configure it" message every other empty node
> gets. [#3533043](https://www.drupal.org/i/3533043)

> ✅ 2026-09-02: Pager, exposed form, rows and the more link configure in
> place, in the Config panel. [#3533043](https://www.drupal.org/i/3533043)

> ✅ 2026-09-01: Views sources render for real wherever a page context is
> available - Preview, and the live page - the same placeholder-in-the-raw-builder
> split every page-only source already uses. [#3542796](https://www.drupal.org/i/3542796)

## See also

- [Display Builder islands](islands.md) - Learn about available UI components
- [Pattern presets](pattern-presets.md) - Reuse component arrangements across
  views
- [Entity view displays](entity-displays.md) - Similar display building for entity
  views
