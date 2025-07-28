# Contributing

## How to contribute

If you'd like to participate in Display Builder development, thank you!

First step is to join our slack [#display_builder](https://drupal.slack.com/archives/C092EUNPCRW)

Display Builder is in active development, codebase can change heavily, be prepared for rebasing while helping us.

### Pull requests

We accept only pull requests (PR), no patches, with the following expectations:

- Maintain the existing code style, CI pass is mandatory. ⚠️ Please ensure your PR is all green on CI before asking for review
- Are focused on a single change (i.e. avoid large refactoring or style adjustments in untouched code if not the primary goal of the pull request) from a single Drupal issue
- Have tests if possible
- Don't decrease the current code coverage

Commit message structure **must** have the issue ID and **can** have the contribution credit:

- ✅ Issue #3529070 by pdureau, mogtofu33: Use PluginSettingsInterface::settingsSummary()
- ✅ Issue #3529070: Use PluginSettingsInterface::settingsSummary()
- ❌ Use PluginSettingsInterface::settingsSummary()

Naming rules:

- "Display Builder" with upper-case "B" for the module
- "Display builder" with lower-case "b" for the config entity (profile) or for a plugin
