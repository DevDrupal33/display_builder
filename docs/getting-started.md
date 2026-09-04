# Build your first display

This tutorial walks you through the core Display Builder workflow: enabling
the builder on an existing display, placing a field and a component with
drag and drop, configuring them, previewing, and publishing the result. It is
the same workflow you reuse for [entity view displays](entity-displays.md),
[page layouts](page-layout.md) and [Views](with-views.md). It uses an entity
view display because that one needs the least setup.

By the end, you will have replaced the static field list of the **Article**
content type's default display with a composition you built visually, and
published it so visitors see the result.

## Prerequisites

- A Drupal **11.4+** site with Display Builder installed. See
  [Installation](install.md).
- A content type with at least one field. This tutorial uses the **Article**
  content type and its **Body** field from Drupal's Standard profile. Any
  content type with a field works the same way.
- At least one published Article, so you have a real page to check at the
  end.
- An administrator account, or a role with these two permissions (see
  [Security model](security-model.md)):
    - **Content: Administer display** (`administer node display`), to
      activate Display Builder on the Article display.
    - **Use the Default Display Builder profile**
      (`use display builder default`), to open the builder.

!!!note
    Display Builder ships a ready-to-use **Default** profile, so you don't
    need the `display_builder_ui` sub-module or a profile of your own to
    follow this tutorial. Install it later to customize which panels and
    tools appear. See [Configuring Display Builder](configuration.md).

## Step 1: Enable the module

Enable the entity view sub-module. Display Builder activates its own
dependencies, UI Patterns and its field sub-modules:

```shell
drush -y en display_builder_entity_view
drush cache:rebuild
```

You can also enable it from **Administration > Extend** (`/admin/modules`).

**Verify:** `drush pm:list --status=enabled | grep display_builder` lists
`display_builder` and `display_builder_entity_view`.

## Step 2: Activate Display Builder on a display

1. Go to **Administration > Structure > Content types > Article > Manage
   display** (`/admin/structure/types/manage/article/display`).
2. In the **Display builder** fieldset, pick **Default** under **Enable with
   profile**.
3. Select **Save**.

![Activate Display Builder](images/entity-view-activate.webp)

**Verify:** the field formatter table is gone, replaced by a large **Display
builder** button, and a second button with the same target sits under the
profile selector:

![Display Builder activated](images/entity-view-activate-2.webp)

!!!note
    Activating Display Builder doesn't remove Layout Builder. Both can coexist
    on the same display, and you can switch back at any time. Only one tool
    renders the content, based on your selection.

## Step 3: Open the builder and find your way around

Select the **Display builder** button.

The builder is made of *islands*, the panels and controls around the
display. With the Default profile you see:

- A sidebar with **Instances** (every display you can jump to),
  **Navigator** (the hierarchy of what you placed) and **Libraries**, whose
  **Components**, **Blocks** and **Presets** tabs hold what you can drop.
- The main area, with the **Canvas** tab (a live preview you drop into) and
  the **Scaffold** tab (the same content as schematic cards).
- The toolbar, with a save status indicator at the start and, at the end,
  the preview toggle, **History** (undo, redo), **State** (Publish, Restore),
  **Expand**, **Help** and **Back**.

![The Libraries panel](images/islands/library.webp)

**Verify:** the Libraries panel, the Canvas and the toolbar are all visible.
The Canvas shows a hint pointing at the Libraries panel, because nothing is
built yet.

To learn what every island does, including the ones the Default profile
leaves off, see [Available islands](islands.md).

## Step 4: Add a field

Open the **Blocks** tab of the Libraries panel. Alongside Drupal blocks, it
lists the sources available in the entity display context, such as the
article's **Title** and **Body** fields:

![Entity display sources](images/entity-display-sources.webp)

Drag **Body** onto the Canvas. While you drag, the drop zones highlight to
show where it can land.

**Verify:** the Body field appears on the Canvas, filled with sample content
generated for the preview.

## Step 5: Add a component

Open the **Components** tab. Components are the Single Directory Components
(SDC) provided by your theme and the modules you installed, for example the
demonstration components of the UI Patterns Library module if it is enabled.

Drag a component onto the Canvas, next to or below the Body field.

![The Canvas](images/islands/builder.webp)

**Verify:** the component renders on the Canvas in its default state.

!!!tip
    Some components are hard to manipulate on the Canvas: a modal, a slider,
    a collapsing accordion. Switch to the **Scaffold** tab (shortcut `g`) to
    drop into a schematic view of the same structure instead.

## Step 6: Configure the element

Select the component on the Canvas. The contextual sidebar opens with a tab
per concern:

- **Config**: the component's props, and its slots.
- **Styles**: style utilities, when the UI Styles module is installed.
- **Visibility**: conditions that decide when the element renders.

Change a prop in **Config**, a heading level or a variant for example, and
watch the Canvas update.

**Verify:** the component on the Canvas reflects your change, without a page
reload.

!!!tip
    Right-click an element on the Canvas for its contextual menu: copy, paste
    and duplicate, copy and paste styles, **Delete**, and **Save as preset**.
    See [Pattern presets](pattern-presets.md) to reuse an arrangement
    elsewhere. Drag an element to another spot to reorder.

## Step 7: Preview the display

Select the preview toggle in the toolbar (shortcut `p`). The **Preview**
pane opens beside the Canvas, rendering the display as visitors see it,
without the builder chrome:

![The Preview pane](images/islands/preview.webp)

Use **Responsive width and Zoom** in the Preview header to check the display
at other breakpoint widths:

![The viewport switcher](images/islands/viewport.webp)

**Verify:** the Preview shows your field and component together, and the
layout adapts as you switch widths.

## Step 8: Publish the display

Display Builder keeps your work in a temporary working storage as you build.
It does **not** save to configuration on its own, so nothing is live until
you publish.

Select **Publish** in the toolbar's **State** buttons:

![State buttons](images/state-buttons.webp)

The State buttons offer:

- **Publish**: write the current display to configuration.
- **Restore**: load the display currently published in configuration,
  discarding unpublished changes.
- **Revert**: return an entity view override to its base display. Not shown
  here, it only applies to
  [overrides](entity-displays-overrides.md).

**Verify:** the save status indicator at the start of the toolbar reports the
publish, and **Publish** is disabled until you change something again.

!!!warning
    Your in-progress work lives in a temporary
    [instance](instances.md) until you publish. Instances are not
    synchronized between environments and are cleaned up during module
    updates. Always publish before updating the module.

## Step 9: View the result on the front end

Visit a published article, for example `/node/1`.

**Verify:** the article renders with the field and the component you
arranged, in the order you gave them, instead of the default field formatter
output.

You built and shipped your first display.

## Next steps

- [Entity view displays](entity-displays.md): the reference for the workflow
  in this tutorial, and how to apply it to other view modes like teasers.
- [Entity view displays overrides](entity-displays-overrides.md): let content
  editors customize the display of one item.
- [Page layout](page-layout.md): use the same workflow to replace Block
  Layout for whole pages.
- [Use Display Builder with Views](with-views.md): apply it to a View's
  display regions.
- [How displays nest](how-displays-nest.md): which builder owns what on a
  page, once you have more than one.
- [Configuring Display Builder](configuration.md): tailor the panels and
  tools with profiles, for example a restricted component library for content
  editors.

If something looks wrong along the way, see
[Display Builder is acting weird, what can I do?](faq.md#display-builder-is-acting-weird-what-can-i-do)
in the FAQ.
