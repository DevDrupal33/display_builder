# UI Layout

## Layers & Z indexes

Display Builder's layout is using Drupal Core's Navigation Toolbar and [Shoelace design system](https://shoelace.style/), so it is organized around the layers of both, from upper to lower levels:

| Element                                                  | Variable                        | Value | Source     |
| -------------------------------------------------------- | ------------------------------- | ----- | ---------- |
| .db-menu, .db-preview                                    | --sl-z-index-dropdown           | 1253  | Shoelace   |
| .display-builder--fullscreen, shoelace-drawer:part(base) | --sl-z-index-drawer             | 1252  | Shoelace   |
| .admin-toolbar                                           | --admin-toolbar-z-index         | 1251  | Navigation |
|                                                          | --sl-z-index-tooltip            | 1000  | Shoelace   |
|                                                          | --sl-z-index-toast              | 950   | Shoelace   |
|                                                          | --sl-z-index-dialog             | 800   | Shoelace   |
| .top-bar                                                 | --admin-toolbar-z-index-top-bar | 490   | Navigation |

Drupal Core's Toolbar and Contrib's Admin Toolbar can't be used with Navigation Toolbar, so their layers are not supported:

| Element                                       | Default value | Source  |
| --------------------------------------------- | ------------- | ------- |
| .toolbar .toolbar-bar, .toolbar .toolbar-tray | 1250          | Toolbar |
| .toolbar-oriented .toolbar-bar                | 502           | Toolbar |

# Offsets

Elements sharing the same layer must not overlap

Top:

|  Element                     | Variable                                                     | Small screen | Wide screen | Source           |
| ---------------------------- | ------------------------------------------------------------ | ------------ | ----------- | ---------------- |
| #db-second-drawer            | --drupal-displace-offset-top, --admin-toolbar-top-bar-height | 96px         | 64px        | Core, Navigation |
| .display-builder--fullscreen | --drupal-displace-offset-top, --admin-toolbar-top-bar-height | 96px         | 64px        | Core, Navigation |

Start:

|  Element | Variable                      | Default value | Source     |
| -------- | ----------------------------- | ------------- | ---------- |
|          | --drupal-displace-offset-left | 64px          | Navigation |
