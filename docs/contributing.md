# Contributing

## How to contribute

If you'd like to participate in Display Builder development, thank you!

First step is to join our slack [#display_builder](https://drupal.slack.com/archives/C092EUNPCRW)

Display Builder is in active development, codebase can change heavily, be prepared for rebasing while helping us.

### Issue

- When fork an issue, use a short branch name with proper wording (no article, no concatenated words, ie: no 300000-mytitleislongandthisbran )
- When a PR is created it MUST be set DRAFT immediately until the issue is in `Need review`

### Pull requests

We accept only pull requests (PR), no patches, with the following expectations:

- Maintain the existing code style, CI pass is mandatory. ⚠️ Please ensure your PR is all green on CI before asking for review, see Code style below for local setup
- Are focused on a single change (i.e. avoid large refactoring or style adjustments in untouched code if not the primary goal of the pull request) from a single Drupal issue
- Contributor is responsible for rebase and MUST do it before review, exception on Draft that require review
- Have tests if possible
- Don't decrease the current code coverage

#### Commit

- Use new Drupal contribution record system:

> [#ISSUE_ID] SCOPE: DESCRIPTION
>
> By: CREDIT 1  
> By: CREDIT 2  
> ...

#### Review

- All comments on code SHOULD be done as much as possible with the Gitlab comment system to allow `resolve`
- All remarks MUST be `resolved` before merging

#### Naming rules

- "Display Builder" with upper-case "B" for the module
- "Display builder" with lower-case "b" for the config entity (profile) or for a plugin

### Code style and lint

Install all qa tools for this project in your Drupal root:

```shell
composer require --dev \
  drupal/coder dealerdirect/phpcodesniffer-composer-installer \
  phpstan/phpstan mglaman/phpstan-drupal phpstan/phpstan-deprecation-rules \
  phpmd/phpmd \
  drupol/phpcsfixer-configs-drupal friendsofphp/php-cs-fixer \
  friendsoftwig/twigcs vincentlanglet/twig-cs-fixer
```

PHPcs, use config `.phpcs.xml` in this project, from Drupal root:

```shell
composer require --dev drupal/coder dealerdirect/phpcodesniffer-composer-installer # Install first time

vendor/bin/phpcs web/modules/custom/display_builder --basepath=$(pwd) --standard=web/modules/custom/display_builder/.phpcs.xml
```

PHP-cs-fixer, use config `.php-cs-fixer.php` in this project, from Drupal root:

```shell
compose require --dev drupol/phpcsfixer-configs-drupal friendsofphp/php-cs-fixer # Install first time

vendor/bin/php-cs-fixer fix --config=web/modules/custom/display_builder/.php-cs-fixer.php --allow-risky=yes web/modules/custom/display_builder/
```

PHPStan, see `phpstan.neon` in this project, from Drupal root:

```shell
composer require --dev phpstan/phpstan mglaman/phpstan-drupal phpstan/phpstan-deprecation-rules # Install first time

vendor/bin/phpstan analyse --configuration=web/modules/custom/display_builder/phpstan.neon --memory-limit=1G web/modules/custom/display_builder/
```

PHPmd, see `.phpmd.xml` in this project, from Drupal root:

_Note_ As the baseline is for Drupal ci usage, need a quick fix.

```shell
composer require --dev phpmd/phpmd # Install first time

## Edit the baseline paths
sed -i "s#/builds/project/display_builder#web/modules/custom/display_builder#g" web/modules/custom/display_builder/.phpmd.baseline.xml
# Run the phpmd
vendor/bin/phpmd web/modules/custom/display_builder --exclude 'tests/*,**/tests/*' text web/modules/custom/display_builder/.phpmd.xml --baseline-file web/modules/custom/display_builder/.phpmd.baseline.xml

## Restore the baseline for ci
sed -i "s#web/modules/custom/display_builder#/builds/project/display_builder#g" web/modules/custom/display_builder/.phpmd.baseline.xml
```

Twig, from Drupal root:

```shell
composer require --dev friendsoftwig/twigcs vincentlanglet/twig-cs-fixer # Install first time

vendor/bin/twig-cs-fixer lint --fix web/modules/custom/display_builder
vendor/bin/twigcs web/modules/custom/display_builder
```

Javascript and Yaml, from Drupal root:

```shell
cd web/core && yarn install # Install first time
cd ../..
cp web/core/.prettierrc.json web/modules/custom/display_builder/.prettierrc.json
web/core/node_modules/.bin/eslintt --fix --config=web/core/.eslintrc.json \
  --ignore-path web/modules/custom/display_builder/.eslintignore \
  --no-error-on-unmatched-pattern --ext .js,.yml \
  --resolve-plugins-relative-to ./web/core/node_modules web/modules/custom/display_builder
rm -f web/modules/custom/display_builder/.prettierrc.json
```

Stylesheet, from Drupal root:

```shell
cd web/core && yarn install # Install first time

web/core/node_modules/.bin/stylelint --config web/core/.stylelintrc.json --fix \
  "web/modules/custom/display_builder/assets/css/*.css" \
  "web/modules/custom/display_builder/components/**/*.css"
```

Markdown, from Drupal root:

```shell
cd web/core && yarn install # Install first time

web/core/node_modules/.bin/prettier --config=web/core/.prettierrc.json \
  --ignore-path web/modules/custom/display_builder/.eslintignore \
  --log-level warn --write web/modules/custom/display_builder/docs/
```

Components, from Drupal root:

```shell
composer require --dev drupal/sdc_devel # Install first time
# Enable the SDC Devel module, then:
vendor/bin/drush sdc:validate display_builder
```
