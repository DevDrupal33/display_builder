## [1.0.0-beta5] - 2026-06-26

### 🚀 Features

- Instance performance with tree cache
- [#3579216](https://www.drupal.org/project/display_builder/issues/3579216) Page layouts must be built in a full page builder
- [#3562989](https://www.drupal.org/project/display_builder/issues/3562989) Implements RevisionLogInterface for Instance entity
- [#3571038](https://www.drupal.org/project/display_builder/issues/3571038) Render extra field values
- [#3534619](https://www.drupal.org/project/display_builder/issues/3534619) Support for legacy preprocesses

### 🐛 Bug Fixes

- Mismatched entity after update
- [#3581893](https://www.drupal.org/project/display_builder/issues/3581893) beta fixes and cleanup
- [#3581893](https://www.drupal.org/project/display_builder/issues/3581893) minor fixes on architecture and tests
- [#3581893](https://www.drupal.org/project/display_builder/issues/3581893) form base regression, require lazy service init, add test
- [#3572328](https://www.drupal.org/project/display_builder/issues/3572328) Always prefer imported view display over saved state
- [#3593682](https://www.drupal.org/project/display_builder/issues/3593682) DisplayBuilderHelpers : use Markup object when injecting html as string
- [#3573122](https://www.drupal.org/project/display_builder/issues/3573122) Error when saving a presets after a migration from Layout Builder
- [#3549567](https://www.drupal.org/project/display_builder/issues/3549567) TokenSource::getContextDefinitions() always return entity
- Uniqid is not so uniq, replace with random_bytes
- [#3595403](https://www.drupal.org/project/display_builder/issues/3595403) Entity template override
- *(typo)* Text fixes
- [#3603094](https://www.drupal.org/project/display_builder/issues/3603094) Skip config import if no instance entity
- Resolve [#3597233](https://www.drupal.org/project/display_builder/issues/3597233) "Drupal 12 compatibility fixes"
- Revert "Resolve [#3597233](https://www.drupal.org/project/display_builder/issues/3597233) "Drupal 12 compatibility fixes""
- [#3579298](https://www.drupal.org/project/display_builder/issues/3579298) component library search was removed

### 💼 Other

- [#3577791](https://www.drupal.org/project/display_builder/issues/3577791) Adopt Field API for Instance entity data
- [#3582234](https://www.drupal.org/project/display_builder/issues/3582234) Split publishing logic from state logic
- [#3582234](https://www.drupal.org/project/display_builder/issues/3582234) Split publishing logic from state logic - simplify
- [#3593112](https://www.drupal.org/project/display_builder/issues/3593112) Remove entity_operation_alter hooks
- [#3593247](https://www.drupal.org/project/display_builder/issues/3593247) Move some entity view logic to buildable plugins
- [#3592749](https://www.drupal.org/project/display_builder/issues/3592749) Consistency of revision logs messages
- Resolve [#3606114](https://www.drupal.org/project/display_builder/issues/3606114) "Docs, translations and changelog"
- Revert "revert: 21d1d216, entity sync with config"

### 🚜 Refactor

- [#3579298](https://www.drupal.org/project/display_builder/issues/3579298) Simpler ComponentLibrary configuration

### 📚 Documentation

- [#3580549](https://www.drupal.org/project/display_builder/issues/3580549) Update documentation
- [#3582234](https://www.drupal.org/project/display_builder/issues/3582234) fix leftover doc
- Minor playwright test fix

### 🎨 Styling

- Fix phpstan new offsetAssign.dimType error

### 🧪 Testing

- Update
- Update and split Playwright tests
- Fix playwright snapshot
- Typo and doc
- Update and split tests

### ⚙️ Miscellaneous Tasks

- *(instance)* Update for content type change of instance
- Add github actions to run Playwright
- [#3578224](https://www.drupal.org/project/display_builder/issues/3578224) add getPast() and getFuture() to HistoryInterface
- [#3578999](https://www.drupal.org/project/display_builder/issues/3578999) Pass existing Instance object to islands methods instead of loading it again
- Temporary patch on ui_patterns for comment form
- Remove legacy ui_patterns patch

### ◀️ Revert

- 21d1d216, entity sync with config
## [1.0.0-beta4] - 2026-03-10

### 🚀 Features

- [#3576918](https://www.drupal.org/project/display_builder/issues/3576918) Specific first log messages according to the data source
- [#3562686](https://www.drupal.org/project/display_builder/issues/3562686) Entity view override enhancements

### 🐛 Bug Fixes

- [#3575406](https://www.drupal.org/project/display_builder/issues/3575406) Issues with token modal window
- [#3577023](https://www.drupal.org/project/display_builder/issues/3577023) Don't count empty steps as proper steps
- [#3576984](https://www.drupal.org/project/display_builder/issues/3576984) Error: [attachToSlot] moveToSlot failed with invalid data
- [#3576984](https://www.drupal.org/project/display_builder/issues/3576984) Error: [attachToSlot] moveToSlot failed with invalid data
- [#3577332](https://www.drupal.org/project/display_builder/issues/3577332) Field management in Entity view override

### 💼 Other

- Revert [#3576984](https://www.drupal.org/project/display_builder/issues/3576984) Source tree solution for invalid data

### 🚜 Refactor

- *(views)* [#3577308](https://www.drupal.org/project/display_builder/issues/3577308) Use ViewDisplay::collectInstances() in ViewsManagementController

### 🧪 Testing

- Fix instance history tests
- [#3578068](https://www.drupal.org/project/display_builder/issues/3578068) update and rework

### ⚙️ Miscellaneous Tasks

- Update ui_patterns dependency
## [1.0.0-beta3] - 2026-03-03

### 🚀 Features

- [#3549266](https://www.drupal.org/project/display_builder/issues/3549266) Move DisplayBuildableInterface to a new plugin type
- [#3547979](https://www.drupal.org/project/display_builder/issues/3547979) Import initial config from block layout
- [#3534217](https://www.drupal.org/project/display_builder/issues/3534217) Use groups in Pattern presets library

### 🐛 Bug Fixes

- [#3572086](https://www.drupal.org/project/display_builder/issues/3572086) Remove core patch that has been committed to 11.3
- [#3548498](https://www.drupal.org/project/display_builder/issues/3548498) Entity view: handle block content
- [#3574548](https://www.drupal.org/project/display_builder/issues/3574548) Unexpected properties in ComponentSource data
- [#3542859](https://www.drupal.org/project/display_builder/issues/3542859) Migration from Layout Builder (content overrides)
- [#3573805](https://www.drupal.org/project/display_builder/issues/3573805) Empty renderables in regions prevent layouts rendering
- [#3574921](https://www.drupal.org/project/display_builder/issues/3574921) Instance::moveToSlot() error when an ancestor is a not a SDC
- [#3575913](https://www.drupal.org/project/display_builder/issues/3575913) Compatibility with JSON API, part 2
- [#3576464](https://www.drupal.org/project/display_builder/issues/3576464) Instances are recreated on load

### 💼 Other

- [#3575339](https://www.drupal.org/project/display_builder/issues/3575339) Clean leftovers of first Declarative Shadow Dom attempt

### 🚜 Refactor

- [#3563264](https://www.drupal.org/project/display_builder/issues/3563264) apply AutowireTrait and update constructor signatures for improved dependency injection

### 📚 Documentation

- Ci playwright link and patches url

### 🧪 Testing

- [#3576464](https://www.drupal.org/project/display_builder/issues/3576464) update instance tests
- Normalize tests comments
- Update Playwright tests
- Fix style and add layers test
- Update snapshots, ignore phpcs on pw snapshots

### ⚙️ Miscellaneous Tasks

- [#3529495](https://www.drupal.org/project/display_builder/issues/3529495) UI Styles must be a soft dependency
- Fix playwright link
## [1.0.0-beta2] - 2026-01-30

### 🚀 Features

- [#3555920](https://www.drupal.org/project/display_builder/issues/3555920) Make islands' region system more generic
- Detect empty form and display message
- Add patterns token back
- [#3544539](https://www.drupal.org/project/display_builder/issues/3544539) Unexpected keyboard shortcut event
- [#3531521](https://www.drupal.org/project/display_builder/issues/3531521) Layout plugins support

### 🐛 Bug Fixes

- Remove admin permission on view page layout
- Php warning config summary
- Sets user context value if condition plugin is using it
- Hides 'current_theme' condition plugin in visibility conditions
- Remove condition plugins from config if they match their default configuration
- [#3564094](https://www.drupal.org/project/display_builder/issues/3564094) config summary
- Multiple fixex post beta1
- [#3570261](https://www.drupal.org/project/display_builder/issues/3570261) Empty third_party_settings stored as a string instead of array
- [#3563264](https://www.drupal.org/project/display_builder/issues/3563264) add logging and remove test source and component
- [#3563264](https://www.drupal.org/project/display_builder/issues/3563264) do not print config on empty value
- [#3570382](https://www.drupal.org/project/display_builder/issues/3570382) Fatal: Compatibility with JSON API

### 💼 Other

- Fix failing summary on preset
- Fix doc and patch

### 🚜 Refactor

- Document duplicate page layout feature and clean comments

### 📚 Documentation

- Update style and fix links
- Update documentation for beta

### 🎨 Styling

- Missing space
- Fix newline on yaml
- Minor php-cs-fixer run and deprecation update

### 🧪 Testing

- *(ci)* Fix random missing phpmd file
- Update tests and snapshots
- *(ci)* Tests speedup and update
- Update and style fix, add playwright video on fail
- *(ci)* Remove webkit tests
- Update e2e test, add canary, update doc
- Add ComponentSource test
- Fix errors

### ⚙️ Miscellaneous Tasks

- *(ui_patterns)* Remove patch and bump last ui_patterns release
## [1.0.0-beta1] - 2026-01-10

### 🚀 Features

- [#3545474](https://www.drupal.org/project/display_builder/issues/3545474) Security: Set Instance access handler
- [#3547972](https://www.drupal.org/project/display_builder/issues/3547972) Block library: feedbacks
- [#3561513](https://www.drupal.org/project/display_builder/issues/3561513) Provide a way to show styles
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) instance list filters, preview blocks, layers fixes
- [#3549867](https://www.drupal.org/project/display_builder/issues/3549867) First step is not properly savable
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) scroll when higlight from tree
- [#3563685](https://www.drupal.org/project/display_builder/issues/3563685) HTMX requests are retriggering SSE
- [#3564094](https://www.drupal.org/project/display_builder/issues/3564094) Add component config summary on layers
- Add sdc devel kernel test, fix components

### 🐛 Bug Fixes

- Page title and content custom placeholder
- [#3557112](https://www.drupal.org/project/display_builder/issues/3557112) BuilderPanel: blocks with many root HTML elements are not draggables
- [#3549761](https://www.drupal.org/project/display_builder/issues/3549761) Fatal: Default value for props, on update
- [#3547955](https://www.drupal.org/project/display_builder/issues/3547955) Components library: handle replaces
- [#3557824](https://www.drupal.org/project/display_builder/issues/3557824) Missing title in EntityViewOverridesController
- [#3558768](https://www.drupal.org/project/display_builder/issues/3558768) Instance is not saved after preset attachment
- [#3555475](https://www.drupal.org/project/display_builder/issues/3555475) Create new revision when a view override is published
- [#3559827](https://www.drupal.org/project/display_builder/issues/3559827) Page layout action "clear" access error
- [#3560985](https://www.drupal.org/project/display_builder/issues/3560985) Profile::getCacheTags() are missing from ProfileViewbuilder
- [#3538724](https://www.drupal.org/project/display_builder/issues/3538724) Experimental excluded from default profile
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) Remove tree, tokens and visibility from default profile.
- [#3561476](https://www.drupal.org/project/display_builder/issues/3561476) Beta fixes on search, preview
- [#3561716](https://www.drupal.org/project/display_builder/issues/3561716) Preset name with utf8 will crash all builders
- [#3561411](https://www.drupal.org/project/display_builder/issues/3561411) Drag problem: after adding a style to a component, cannot drag in component slot
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) layers and instance admin page
- [#3535999](https://www.drupal.org/project/display_builder/issues/3535999) Render UI with both front and admin theme
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) layers fix, add description switch
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) drawer and toolbar z-index, update views test
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) dark mode and z-index mess
- [#3561312](https://www.drupal.org/project/display_builder/issues/3561312) working conditions when context mapping
- Remove deleted test
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) some css issue on drag and sticky toolbar
- [#3562991](https://www.drupal.org/project/display_builder/issues/3562991) instance list fom config
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) ui style fixes, prepare tree move
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) minor layer ui adjustments
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) copy paste fix and some layers css
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) remove rc2 core patch
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) better layer colors
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) module required for islands
- [#3563455](https://www.drupal.org/project/display_builder/issues/3563455) Fatal: island plugin not found in 3rd party settings
- [#3563138](https://www.drupal.org/project/display_builder/issues/3563138) Instance list refactor

### 💼 Other

- [#3553337](https://www.drupal.org/project/display_builder/issues/3553337) feat: Move renderable alterations from ProfileViewBuilder to IslandInterface
- [#3555179](https://www.drupal.org/project/display_builder/issues/3555179) task: 11.3 compat: Proper DI for BareHtmlPageRenderer
- [#3533044](https://www.drupal.org/project/display_builder/issues/3533044) fix: Merge the two ViewRowsSource plugins
- [#3539301](https://www.drupal.org/project/display_builder/issues/3539301) feat: Page render cache and page_cache_kill_switch
- [#3546356](https://www.drupal.org/project/display_builder/issues/3546356) Update documentation
- [#3561474](https://www.drupal.org/project/display_builder/issues/3561474): WYSIWYG block behavior problems with ajax
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) hide description not working
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) usability of generics
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) ui_skins as soft dependency
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) duplicate mehods
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) p is not a section

### 📚 Documentation

- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) Update required Drupal version to 11.3
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) update documentation and bump versions
- Update install and add patches

### 🎨 Styling

- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) fix css
- Fix phpcs
- Styling issue on hotfix

### 🧪 Testing

- Fix toolbar based on config
- Init test for Instance history
- [#3557196](https://www.drupal.org/project/display_builder/issues/3557196) Add tests for BuilderPanel::buildSingleBlock()
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) fix playwright with views
- Some non ci tests fix
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) update theme test
- Update tests and doc
- Comment webserver launch on pw

### ⚙️ Miscellaneous Tasks

- [#3556198](https://www.drupal.org/project/display_builder/issues/3556198) Minimal requirement is now Drupal 11.3.x
- Reduce playwright verbosity
- [#3529064](https://www.drupal.org/project/display_builder/issues/3529064) Follow Core 11.3's HTMX integration
- [#3560331](https://www.drupal.org/project/display_builder/issues/3560331) Clean slot_sources_proxy dependencies
- [#3555193](https://www.drupal.org/project/display_builder/issues/3555193) Navigation look without navigation module
- Layout builder patch update with 11.3-beta1
- [#3561854](https://www.drupal.org/project/display_builder/issues/3561854) Node & Instance naming normalization
- [#3561975](https://www.drupal.org/project/display_builder/issues/3561975) Rename _third_party_settings with third_party_settings
- [#3561447](https://www.drupal.org/project/display_builder/issues/3561447) reove token block, textfield must be used
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) remove wrong interface
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) some styling changes and optimization
- [#3562556](https://www.drupal.org/project/display_builder/issues/3562556) Instances admin page improvements
- [#3562556](https://www.drupal.org/project/display_builder/issues/3562556) Instances admin page improvements
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) Entity override deletion
- [#3538724](https://www.drupal.org/project/display_builder/issues/3538724) Provide some generic components
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) remove debug flag
- [#3562410](https://www.drupal.org/project/display_builder/issues/3562410) Rename UI Styles & UI Skins plugins ID
- [#3562991](https://www.drupal.org/project/display_builder/issues/3562991) Instances list from config
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) ui_styles must be a dependency
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) typo, perf refactor, ui patterns patch url
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) add highlight and drawer size memory
- [#3561038](https://www.drupal.org/project/display_builder/issues/3561038) fix tree and selection highlight

### ◀️ Revert

- [#3535999](https://www.drupal.org/project/display_builder/issues/3535999) Render UI with both front and admin theme
## [1.0.0-alpha6] - 2025-10-13

### 🐛 Bug Fixes

- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: some ui fixes and css
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) feat: General bug fixes and code cleanup
- Typo

### 💼 Other

- [#3544785](https://www.drupal.org/project/display_builder/issues/3544785) chore: new logo v2
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) tests update test, add dev tests back
- [#3544785](https://www.drupal.org/project/display_builder/issues/3544785) chore: new logo v3
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) style: cspell fix
- [#3544785](https://www.drupal.org/project/display_builder/issues/3544785) chore: new logo v4
- [#3544785](https://www.drupal.org/project/display_builder/issues/3544785) remove tryout logo
- [#3538360](https://www.drupal.org/project/display_builder/issues/3538360) chore: Profile & Instance naming normalization
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) update test playwright, bring back dev tools based tests
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) style: tests style fix
- [#3544303](https://www.drupal.org/project/display_builder/issues/3544303) feat: Simplify logs panel implementation
- [#3544543](https://www.drupal.org/project/display_builder/issues/3544543) feat: Some presets are not saved correctly
- [#3546198](https://www.drupal.org/project/display_builder/issues/3546198) feat: Add display_builder_entity_form sub-module
- Revert "[#3546198](https://www.drupal.org/project/display_builder/issues/3546198) feat: Add display_builder_entity_form sub-module"
- [#3545608](https://www.drupal.org/project/display_builder/issues/3545608) feat: Merge some buttons into a Controls island
- [#3545303](https://www.drupal.org/project/display_builder/issues/3545303) feat: Island class clean, exclude instead of allowed
- [#3545164](https://www.drupal.org/project/display_builder/issues/3545164) feat: SSE: better cache and tempstore management
- Css drawer top margin in fullscreen
- [#3545303](https://www.drupal.org/project/display_builder/issues/3545303) Island Plugin refactor and rationalization
- [#3544033](https://www.drupal.org/project/display_builder/issues/3544033) feat: Views with contextual filters
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) tests: fix tests with top toolbar
- [#3547315](https://www.drupal.org/project/display_builder/issues/3547315) fix: Missing javascript core/internal.jquery.form
- [#3547962](https://www.drupal.org/project/display_builder/issues/3547962) feat: HtmlResponseAttachmentsProcessor dependency breaks when a decorator is used
- *(https://www.drupal.org/project/display_builder/issues/3545319)* Fix js disable link on update
- *(https://www.drupal.org/project/display_builder/issues/3545319)* Doc: update jsdoc
- *(https://www.drupal.org/project/display_builder/issues/3545319)* Cache page kill for each builder
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) feat: block preview was lost, put back
- [#3547973](https://www.drupal.org/project/display_builder/issues/3547973) feat: Library: fix preview z-index with Navigation
- [#3547972](https://www.drupal.org/project/display_builder/issues/3547972) feat: Block library: feedbacks
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: block search, drawer open on move and uneeded cache
- [#3531253](https://www.drupal.org/project/display_builder/issues/3531253) feat: Dynamic drawer labels
- [#3547325](https://www.drupal.org/project/display_builder/issues/3547325) task: Parent display link & Instance form: Wording & naming
- Revert "fix: typo (wrong branch)"
- [#3547945](https://www.drupal.org/project/display_builder/issues/3547945) feat: Call to a member function getPath() on null
- [#3545219](https://www.drupal.org/project/display_builder/issues/3545219) feat: Profile entity: From enable to status
- [#3549150](https://www.drupal.org/project/display_builder/issues/3549150) fix: Fatal errors when using pseudo fields
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: update config and fix wrong schema type
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: refactor drawer js and preview
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: preview doubling
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: rename _node_id to node_id
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) tests: update tests, add preview and drawer
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: preview events
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: olivero z-index header over end drawer
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) fix: css fix for higlight and form
- [#3530856](https://www.drupal.org/project/display_builder/issues/3530856) task: remove the 3 vertical dots menu from TreePanel until [#3547332](https://www.drupal.org/project/display_builder/issues/3547332)
- [#3538207](https://www.drupal.org/project/display_builder/issues/3538207) feat: shoelace library local switch from state
- [#3549550](https://www.drupal.org/project/display_builder/issues/3549550) fix: Update SourceWithChoicesInterface label calls
- [#3547503](https://www.drupal.org/project/display_builder/issues/3547503) feat: Conditional save button for draggable list builders
- [#3549348](https://www.drupal.org/project/display_builder/issues/3549348) fix: Fatal: Default value for props, on load
- [#3545319](https://www.drupal.org/project/display_builder/issues/3545319) style: clean commented js
- [#3540610](https://www.drupal.org/project/display_builder/issues/3540610) feat: Context management for Pattern presets
- [#3547436](https://www.drupal.org/project/display_builder/issues/3547436) feat: Remove dev tools islands from default profile
- [#3550151](https://www.drupal.org/project/display_builder/issues/3550151) fix: SDC from modules must declare empty props
- [#3540610](https://www.drupal.org/project/display_builder/issues/3540610) fix: unrelated remove dependency on ui_patterns_field_formatters
- [#3550902](https://www.drupal.org/project/display_builder/issues/3550902) fix: Add htmx_see library from Collaboration island
- [#3544545](https://www.drupal.org/project/display_builder/issues/3544545) fix: Profile changed in config don't update the instance entity
- [#3551297](https://www.drupal.org/project/display_builder/issues/3551297) fix: Quick-wins UX & profile config page layout
- [#3551640](https://www.drupal.org/project/display_builder/issues/3551640) feat: UX: Improve IslandPluginToolbarButtonConfigurationBase
- [#3551829](https://www.drupal.org/project/display_builder/issues/3551829) fix: Move SSE attachement to ProfileViewBuilder

### 📚 Documentation

- Update issue, commit and review

### 🎨 Styling

- Fix cspell on doc

### ⚙️ Miscellaneous Tasks

- Fix eslint job that now require only single quotes in yaml
- Clean some tests, ignore phpcs cache, fix deprecation, ignore some files for coverage
## [1.0.0-alpha5] - 2025-09-05

### 🐛 Bug Fixes

- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: alpha bug fixes and code cleanup
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) tests: alpha bug fixes and typo fix
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) tests: alpha bug fixes and typo fix

### 💼 Other

- [#3534137](https://www.drupal.org/project/display_builder/issues/3534137) feat: add real-time collaboration
- [#3542000](https://www.drupal.org/project/display_builder/issues/3542000) fix: split DisplayBuilder logic into a view_builder handler
- [#3539075](https://www.drupal.org/project/display_builder/issues/3539075) by sea2709: Dropzone flickering
- [#3542271](https://www.drupal.org/project/display_builder/issues/3542271) feat: make SSE optional
-  [#3542634](https://www.drupal.org/project/display_builder/issues/3542634) fix: remove styles level in UI Styles configuration
- [#3540078](https://www.drupal.org/project/display_builder/issues/3540078) fix: dynamic theme registry alteration for Views and tests update
- [#3541423](https://www.drupal.org/project/display_builder/issues/3541423) fix: add WithDisplayBuilderInterface::checkInstanceId()
- [#3542881](https://www.drupal.org/project/display_builder/issues/3542881) fix: theme registry entry for Entity view display
- [#3543335](https://www.drupal.org/project/display_builder/issues/3543335) fix: navigation top bar not present on entity overrides
- [#3529125](https://www.drupal.org/project/display_builder/issues/3529125) feat: add "Revert" button in State island
- [#3543337](https://www.drupal.org/project/display_builder/issues/3543337) fix: do not translate logs
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) feat: update Playwright tests
- [#3543070](https://www.drupal.org/project/display_builder/issues/3543070) chore: move display_builder_devel to its own contrib project
- [#3543495](https://www.drupal.org/project/display_builder/issues/3543495) by pdureau, mogtofu33, grimreaper: Adopt Entity API for state mgmt, part 1: Façades only
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) style: alpha code cleanup
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) tests: fix and add views test
- [#3543952](https://www.drupal.org/project/display_builder/issues/3543952) feat: adopt Entity API for state mgmt part 2: move logic
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) style: cspell fix
- [#3543952](https://www.drupal.org/project/display_builder/issues/3543952) fix: missing menu and link
- [#3531269](https://www.drupal.org/project/display_builder/issues/3531269) fix: do not replace unchanged panels
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) style: fix pages and style
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) tests: fix tests urls in ci
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) tests: fix page random condition link
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) tests: no page aria match in ci for now
- [#3544569](https://www.drupal.org/project/display_builder/issues/3544569) feat: Support Navigation
- [#3531523](https://www.drupal.org/project/display_builder/issues/3531523) feat: migration from Layout Builder (config only)
- [#3538607](https://www.drupal.org/project/display_builder/issues/3538607) feat: island parent button
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: some logic, entity operation, ui
- [#3544921](https://www.drupal.org/project/display_builder/issues/3544921) fix: SSE and Layout Builder import
- [#3544593](https://www.drupal.org/project/display_builder/issues/3544593) fix: reference field in instance form
- [#3544785](https://www.drupal.org/project/display_builder/issues/3544785) chore: new logo

### 🧪 Testing

- Minor tests fix and update

### ⚙️ Miscellaneous Tasks

- Fix script
## [1.0.0-alpha4] - 2025-08-22

### 🐛 Bug Fixes

- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by mogtofu33: Alpha bug fixes
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) style: some style fixes
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by mogtofu33, grimreaper: Alpha bug fixes and code cleanup
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by mogtofu33: Alpha bug fixes and code cleanup
- [#3541276](https://www.drupal.org/project/display_builder/issues/3541276) by mogtofu33: Run Playwright local, minor fixes and doc

### 💼 Other

- [#3538194](https://www.drupal.org/project/display_builder/issues/3538194) by pdureau, mogtofu33: WithDisplayBuilderInterface follow-ups
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by grimreaper: Typo fix
- [#3538186](https://www.drupal.org/project/display_builder/issues/3538186) by pdureau: ThemeRegistryAlter must fallback on default templates
- [#3539519](https://www.drupal.org/project/display_builder/issues/3539519) by pdureau: Split DisplayBuilderForm::form() to ease maintenance
- [#3539488](https://www.drupal.org/project/display_builder/issues/3539488) by pdureau: Tidy DisplayBuilderHelpers
- [#3538607](https://www.drupal.org/project/display_builder/issues/3538607) by pdureau, mogtofu33: Pre task: consistent paths dash
- [#3536263](https://www.drupal.org/project/display_builder/issues/3536263) by grimreaper, pdureau, mogtofu33: Remove Layout Builder dependency
- Sources array check fatal error
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) fix tests and style overall
- [#3534190](https://www.drupal.org/project/display_builder/issues/3534190) by pdureau: Pattern presets storage and dependencies
- [#3538440](https://www.drupal.org/project/display_builder/issues/3538440) by pdureau: Merge island_settings & island_configuration in profiles
- [#3540730](https://www.drupal.org/project/display_builder/issues/3540730) by pdureau: Use HeaderBag in ApiController::saveInstanceAsPreset()
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) by just_like_good_vibes, pdureau: Flatten the Fields list
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) by just_like_good_vibes, pdureau, mogtofu33: Flatten the Fields list, fix missing data skeleton
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) by just_like_good_vibes, pdureau: Flatten the Fields list
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) by just_like_good_vibes, pdureau: Flatten the Fields list
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) by just_like_good_vibes, pdureau: Flatten the Fields list
- [#3540902](https://www.drupal.org/project/display_builder/issues/3540902) by mogtofu33: Fix some components, make stories working
- Too soon for assets packagist shoelace
- [#3540207](https://www.drupal.org/project/display_builder/issues/3540207) by mogtofu33, pdureau, grimreaper: Check LayoutBuilder core patch for Entity view
- [#3529260](https://www.drupal.org/project/display_builder/issues/3529260) by just_like_good_vibes, pdureau: Flatten the Fields list ui_patterns 2.0.8 compatibility
- [#3541276](https://www.drupal.org/project/display_builder/issues/3541276) by mogtofu33: Run Playwright in ci
- [#3541276](https://www.drupal.org/project/display_builder/issues/3541276) by mogtofu33: Run Playwright in ci, clean unused screenshot
- [#3541276](https://www.drupal.org/project/display_builder/issues/3541276) by mogtofu33: Run Playwright local
- [#3529468](https://www.drupal.org/project/display_builder/issues/3529468) by pdureau: Remove ui_patterns_overrides
- [#3541276](https://www.drupal.org/project/display_builder/issues/3541276) by mogtofu33: Run Playwright local, fix tests
- [#3529125](https://www.drupal.org/project/display_builder/issues/3529125) by christian.wiedemann, pdureau, grimreaper, mogtofu33, just_like_good_vibes: Add content display overrides
- [#3541276](https://www.drupal.org/project/display_builder/issues/3541276) by mogtofu33: Playwright tests update
- [#3534190](https://www.drupal.org/project/display_builder/issues/3534190) by pdureau: Pattern presets storage and dependencies
- [#3538435](https://www.drupal.org/project/display_builder/issues/3538435) by pdureau: Manage breakpoints & add viewports switcher
## [1.0.0-alpha3] - 2025-08-01

### 🐛 Bug Fixes

- Issue [#3537137](https://www.drupal.org/project/display_builder/issues/3537137) by pdureau, mogtofu33: UX enhancement, use 'profile' term, style fixes and doc update
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by mogtofu33: Alpha bug fixes
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by mogtofu33: Alpha bug fixes: create a db on the fly when lost or deleted
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) by mogtofu33: Alpha bug fixes

### 💼 Other

- [#3531467](https://www.drupal.org/project/display_builder/issues/3531467) by goz, yannickoo: Navigation left sidebar goes over the left drawer
- [#3536514](https://www.drupal.org/project/display_builder/issues/3536514) by mogtofu33: Remove demo code
- [#3529129](https://www.drupal.org/project/display_builder/issues/3529129) by pdureau, mogtofu33: Availability according to roles or permissions
- [#3534644](https://www.drupal.org/project/display_builder/issues/3534644) by goz, mogtofu33, pdureau: Remove Island's specific Form classes
- [#3536519](https://www.drupal.org/project/display_builder/issues/3536519) by mogtofu33: better z-index, better drawer component, use Drupal displace lib
- [#3529127](https://www.drupal.org/project/display_builder/issues/3529127) by grimreaper: Play nice with layout builder, add core patch
- [#3532088](https://www.drupal.org/project/display_builder/issues/3532088) by pdureau, mogtofu33: Update and lint documentation
- [#3536629](https://www.drupal.org/project/display_builder/issues/3536629) by mogtofu33: Sortable problems
- [#3529737](https://www.drupal.org/project/display_builder/issues/3529737) by pdureau, mogtofu33: Add proper page management
- [#3529067](https://www.drupal.org/project/display_builder/issues/3529067) by christian.wiedemann, mogtofu33, pdureau: Make Island plugins configurable
- [#3538195](https://www.drupal.org/project/display_builder/issues/3538195) by yannickoo: Z-index is still too low to cover Navigation bar
- [#3535989](https://www.drupal.org/project/display_builder/issues/3535989) by pdureau: Add an ActiveUsers island
- [#3537137](https://www.drupal.org/project/display_builder/issues/3537137) by pdureau: Display Builder config entity UX proposals
- [#3531510](https://www.drupal.org/project/display_builder/issues/3531510) by mogtofu33: Contextual menu enhancement
- [#3534215](https://www.drupal.org/project/display_builder/issues/3534215) by pdureau, mogtofu33: Implement WithDisplayBuilderInterface in Views
- [#3534215](https://www.drupal.org/project/display_builder/issues/3534215) by pdureau, mogtofu33: Implement WithDisplayBuilderInterface in Views
- [#3538026](https://www.drupal.org/project/display_builder/issues/3538026) by pdureau, mogtofu33, just_like_good_vibes: Implement WithDisplayBuilderInterface in Entity View
- [#3538673](https://www.drupal.org/project/display_builder/issues/3538673) by yannickoo, mogtofu33: Unable to enable Display builder for an entity view
- [#3538709](https://www.drupal.org/project/display_builder/issues/3538709) by mogtofu33: Add PHPUnit tests
- [#3538026](https://www.drupal.org/project/display_builder/issues/3538026) by pdureau, mogtofu33, just_like_good_vibes: Implement WithDisplayBuilderInterface in Entity View
- [#3532911](https://www.drupal.org/project/display_builder/issues/3532911) by mogtofu33: Testing e2e
- [#3538726](https://www.drupal.org/project/display_builder/issues/3538726) by mogtofu33: Token block label raw
- [#3539129](https://www.drupal.org/project/display_builder/issues/3539129) by pdureau, mogtofu33: TypeError when creating new Page layout
- *(https://www.drupal.org/project/display_builder/issues/3539129)* Add basic tests
- [#3536334](https://www.drupal.org/project/display_builder/issues/3536334) by pdureau, christian.wiedemann, mogtofu33: Add the island configuration forms
## [1.0.0-alpha2] - 2025-07-11

### 🐛 Bug Fixes

- Style some linter fixes
- Css naming for tabs
- Merge branch display_builder:1.0.x into 3532592-alpha-bug-fixes
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: minor fixes, move fixtures
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) ci: allow phpstan with some fixes, move doc phpmd to baseline

### 💼 Other

- *(https://www.drupal.org/project/display_builder/issues/3533345)* Add ui_icons_patterns dependency to display_builder_devel
- [#3532911](https://www.drupal.org/project/display_builder/issues/3532911) by mogtofu33: Testing e2e
- Left sidebar not pushing body
- [#3532088](https://www.drupal.org/project/display_builder/issues/3532088) by pdureau: Init an user documentation
- [#3533046](https://www.drupal.org/project/display_builder/issues/3533046) by pdureau: Reword plugins ID, label & descriptions
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: fixture from theme, theme test update
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: rationalize css, avoid db names in shoelace
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) test: better test component, init 2 fixtures
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: clean css, add test component and fixtures
- [#3534297](https://www.drupal.org/project/display_builder/issues/3534297) by goz: HTMX module is a dependency but requirement is missing from composer.json
- [#3529074](https://www.drupal.org/project/display_builder/issues/3529074) by goz, mogtofu33, pdureau: Better logs
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: db entity view config not set on newly created
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: disable instance form config auto submit until [#3531518](https://www.drupal.org/project/display_builder/issues/3531518) is resolved
- [#3529074](https://www.drupal.org/project/display_builder/issues/3529074) fix: logs in lists and fix summary
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: instance form css cleanup
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: some db ui adjustments
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: search working with DSFR
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: root name highlight fix
- [#3529103](https://www.drupal.org/project/display_builder/issues/3529103) by goz, mogtofu33, pdureau: Remove Island's specific Form classes
- [#3534566](https://www.drupal.org/project/display_builder/issues/3534566) by pdureau: Align event subscribers
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) feat: add fixtures on devel
- Revert "[#3529103](https://www.drupal.org/project/display_builder/issues/3529103) by goz, mogtofu33, pdureau: Remove Island's specific Form classes"
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) style: grey is not valid
- [#3534335](https://www.drupal.org/project/display_builder/issues/3534335) by pdureau: Align config storage properties
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) feat: hash back to logs for debug, fix saved column
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: fullscreen move drawer move db
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: remove specific dsfr style on button
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) feat: operation button island
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: state hash not deleted
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) fix: toolbar more responsive
- [#3532592](https://www.drupal.org/project/display_builder/issues/3532592) ci: allow phpstan failure for v2 until fixed
- [#3529127](https://www.drupal.org/project/display_builder/issues/3529127) by pdureau: Allow to enable Display Builder even when Layout...
- [#3529362](https://www.drupal.org/project/display_builder/issues/3529362) by grimreaper, pdureau: Convert hooks to PHP class and drop Drupal 10.3
## [1.0.0-alpha1] - 2025-06-26

### 🐛 Bug Fixes

- Some stylelint fixes, set ci more strict

### 💼 Other

- [#3527510](https://www.drupal.org/project/display_builder/issues/3527510) by pdureau, just_like_good_vibes, mogtofu33: Move private code to public
- [#3527510](https://www.drupal.org/project/display_builder/issues/3527510) by pdureau, just_like_good_vibes, mogtofu33: Start from the last stable state
- *(https://www.drupal.org/project/display_builder/issues/3529285)* Navigation left sidebar goes over the left sidebar
- [#3529270](https://www.drupal.org/project/display_builder/issues/3529270) by grimreaper, pdureau: Logs panel: fix printed infos
- [#3529262](https://www.drupal.org/project/display_builder/issues/3529262) by grimreaper: Fatal error with paragraph
- [#3529472](https://www.drupal.org/project/display_builder/issues/3529472) by pdureau: Rename DisplayBuilderPreset
- [#3529681](https://www.drupal.org/project/display_builder/issues/3529681) by grimreaper: Update composer.json
- [#3529049](https://www.drupal.org/project/display_builder/issues/3529049) by mogtofu33: Replace sidebars modals by drawers, remove tippy
- [#3530369](https://www.drupal.org/project/display_builder/issues/3530369) by pdureau: Make components from ancestor themes available
- *(https://www.drupal.org/project/display_builder/issues/3531511)* Installation default config broken
- [#3529062](https://www.drupal.org/project/display_builder/issues/3529062) by vanessa.fayard, pdureau: Make toolbar responsive
- [#3529070](https://www.drupal.org/project/display_builder/issues/3529070) by pdureau, mogtofu33: Use PluginSettingsInterface::settingsSummary()
- [#3529195](https://www.drupal.org/project/display_builder/issues/3529195) by mogtofu33: Init mkdocs, add contributing
- [#3531718](https://www.drupal.org/project/display_builder/issues/3531718) by goz, mogtofu33: Second Drawer does not always display on click

### ⚙️ Miscellaneous Tasks

- Fix stylelint
- Fix stylelint again
- Fix cspell
- Get rid of annoying stylelint
- Clean legacy modal code
