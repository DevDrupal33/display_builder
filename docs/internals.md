# Internals

## Entity displays

Display Builder data is stored as a third party settings with those properties:

- `profile`: the Display Builder profile (config entity) in use last time the config was saved
- `sources`: a UI Patterns 2 sources tree

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
    profile: default
    sources: [...]
```

Overview:

![Entity View internal](images/internals/entity-view-internals.webp)

## Entity display overrides

Display Builder data is stored as content field provided by `ui_patterns_field` module, where every field item has those properties:

- `node_id`: The ID of the node in the source tree
- `source_id`: the source plugin ID
- `source`: the source plugin config
- `third_party_settings`: extra configuration, prefixed by module or island plugin ID

The entity view display config entity has additional `override_field` and `override_profile` properties for the field name storing the data and the related Display Builder profile:

```yaml
id: node.article.default
targetEntityType: node
bundle: article
mode: default
content: {}
hidden: {}
third_party_settings:
  display_builder:
    profile: default
    sources: [...]
    override_field: field_full_display
    override_profile: default
```

Overview:

![Overrides internals](images/internals/overrides-internals.webp)

## Page Layouts

Each page is its own config entity

- `profile`: the Display Builder profile (config entity) in use last time the config was saved
- `sources`: a UI Patterns 2 sources tree

Example:

```yaml
id: users
label: Users
weight: -9
profile: default
sources: [...]
conditions:
  request_path:
    id: request_path
    pages: /user/1
    negate: '0'
```

Overview:

![Internals](images/internals/page-layout-internals.webp)

## Views

Display Builder is a `display_extender` plugin with those properties:

- `profile`: the Display Builder profile (config entity) in use last time the config was saved
- `sources`: a UI Patterns 2 sources tree

Example:

```yaml
id: articles
label: Articles
module: views
display:
  page_1:
    id: page_1
    display_title: Page
    display_plugin: page
    position: 1
    display_options:
      title: Articles
      path: articles
      pager: {}
      exposed_form: {}
      empty: {}
      style: {}
      header: {}
      footer: {}
      display_extenders:
        display_builder:
          profile: default
          sources: [...]
```

Overview:

![Internals](images/internals/with-views-internals.webp)
