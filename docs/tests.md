# Tests e2e

E2e tests are done with [Playwright](https://playwright.dev/docs/intro#installing-playwright).

Tests are located in `tests/src/Playwright/Tests`.

## Local installation

If your system match [requirements](https://playwright.dev/docs/intro#system-requirements),
you can install Playwright run from this module folder:

```shell
npm install
npx playwright install --with-deps
```

**Fedora** is not yet supported by Playwright but can work, see this
[issue](https://github.com/microsoft/playwright/issues/29559), a workaround is
to install these packages:

```shell
sudo dnf install -y \
    libicu \
    libjpeg-turbo \
    libwebp \
    flite \
    pcre \
    libffi
```

An run install:

```shell
npm install
npx playwright install
```

## Local tests

Tests are made to run in ci, they can run locally in 2 modes, with an integrated
Drupal installation for tests or just on a local installed Drupal.

For local tests, some modules **MUST** be enabled:

- layout_builder
- display_builder_test
- display_builder_devel
- display_builder_ui
- display_builder_entity_view
- display_builder_page_layout

And the theme **MUST** be `db_theme_test`, unless test is specific for a theme.

Copy and adapt the `.env.dist` file as `.env` to set your environment.

There is different case to run tests with a full installation or on a running
Drupal to avoid the install step.

_Note_: Webkit test can not be run in a non MacOS env.

Commands to run the tests:

```shell
npm run test
```

Or test by tag:

```shell
npx playwright test --project=firefox --grep "@display_builder_min"
```

Or a specific test:

```shell
npx playwright test --project=firefox -g 'Create instance'
```

On local run, you can see what's happening with a running test:

```shell
npx playwright test --project=firefox -g 'Create instance' --headed
```

Or you can run the test step by step:

```shell
npx playwright test --project=firefox -g 'Create instance' --ui
```

More information on [Playwright running and debugging tests](https://playwright.dev/docs/running-tests).
