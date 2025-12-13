# Contributing

## How to contribute

If you'd like to participate in Display Builder development, thank you!

First step is to join our slack [#display_builder](https://drupal.slack.com/archives/C092EUNPCRW)

Display Builder is in active development, codebase can change heavily, be prepared for rebasing while helping us.

### Issue

- When fork an issue, use a short branch meaningful name with proper wording (no article, no concatenated words, ie: no 300000-my-title-is-long-and-this-break )
- When a PR is created it **MUST** be set DRAFT immediately until the issue is in `Need review`

### Pull requests

We accept only pull requests (PR), no patches, with the following expectations:

- Maintain the existing code style, CI pass is mandatory. ⚠️ Please ensure your PR is all green on CI before asking for review
- Are focused on a single change (i.e. avoid large refactoring or style adjustments in untouched code if not the primary goal of the pull request) from a single Drupal issue
- Contributor is responsible for rebase and **MUST** do it before review, exception on Draft that require review

#### Code

- Unless typo or hotfix, any change **MUST** be covered by a test
- Module **MUST** maintain install / uninstall and update workflow (Best effort)
- Trait **MUST** not included anything external, if so it's not a Trait, try first an abstract class or a service. Exception for usage of classes that can be instantiated statically (TranslatableMarkup, URL,...)

#### Review

- All comments on code **MUST** be done with the Gitlab comment system to allow `resolve`
- All remarks **MUST** be `resolved` before merging
- If an issue or MR is not resolved, maintainers can allow a reasonable time to reach consensus, if not resolved the lead maintainer have final decision on merge or close

#### Commit

- Use new Drupal contribution record system
