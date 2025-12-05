# Tests e2e

End-to-end (E2E) tests in Drupal with [Playwright](https://playwright.dev/docs/intro).
Playwright enables reliable end-to-end testing for modern web apps.

Tests are located in `tests/src/Playwright/Tests`.

## Requirements

You must install `Drush` and `drupal/core-dev` in your project:

```sh
composer require drush/drush --dev
composer require drupal/core-dev:^11.3 -W --dev
```

## Quick Start

If your system match [requirements](https://playwright.dev/docs/intro#system-requirements),
you can install Playwright run from this module folder:

```shell
npm install
npx playwright install --with-deps
```

Copy `.env.dist` to `.env`. No need to change anything.

Uncomment `webServer` in `playwright.config.ts`, then run:

```sh
npm run test
```

### Run Example Test Locally with Docker

If you don't want or cannot install Playwright locally, you can run Playwright
server in a Docker container.

See [Playwright Docker documentation](https://playwright.dev/docs/docker#remote-connection)
for more details.

Pull and run Playwright server from this folder:

```sh
docker pull mcr.microsoft.com/playwright:v1.56.1-noble
docker run --add-host=hostmachine:host-gateway -p 3000:3000 --rm --init -it --workdir /home/pwuser --user pwuser mcr.microsoft.com/playwright:v1.56.1-noble /bin/sh -c "npx -y playwright@1.56.1 run-server --port 3000 --host 0.0.0.0"
```

Launch a webserver on Drupal **root**, for example:

```sh
php -S 0.0.0.0:8000
```

Copy `.env.dist` to `.env`, adapt values for first case:

```sh
DRUPAL_TEST_BASE_URL='http://localhost:8000/web'
```

Run the test from this folder:

```sh
PW_TEST_CONNECT_WS_ENDPOINT=ws://127.0.0.1:3000/ npx playwright test --project=firefox
```

Adapt the other variable for an installed Drupal with a database.

### Run Tests Locally

#### Local Installation

If your system meets the [requirements](https://playwright.dev/docs/intro#system-requirements), you can install Playwright from this folder:

```sh
npm install
npx playwright install --with-deps
```

**Fedora** is not officially supported by Playwright, but it can work. See this [issue](https://github.com/microsoft/playwright/issues/29559). As a workaround, install the following packages:

```sh
sudo dnf install -y \
    libicu \
    libjpeg-turbo \
    libwebp \
    flite \
    pcre \
    libffi
```

Then run the install without dependencies:

```shell
npm install
npx playwright install
```

Even if you see some **error** messages, tests should now work on **Fedora**.

#### Local Tests

Tests are designed to run in **GitLab CI**, but they can also run locally with a running Drupal instance, with or without Drupal installed.

Without a local server, with Drupal and Drush installed, you can quickly launch the example test by running:

```sh
npm run test
```

For local tests with installed Drupal you **MUST** enable `extension_discovery_scan_tests` in your **settings.php**.

```php
$settings['extension_discovery_scan_tests'] = TRUE;
```

Modules that **MUST** be enabled:

- layout_builder
- display_builder_test
- display_builder_ui
- display_builder_entity_view
- display_builder_page_layout
- display_builder_dev_tools (external module, must be installed separately)

Theme **MUST** be `display_builder_theme_test` by default, unless test is
specific for a theme.

Copy and adapt the `.env.dist` file as `.env` to set your environment.

There are different cases for running tests with a full installation or on a running Drupal instance to avoid the install step.

_Note_: WebKit tests cannot be run in a non-MacOS environment.

Commands to run the tests with Firefox only:

```shell
npm run test
```

Or test by tag with Firefox only:

```shell
npx playwright test --project=firefox --grep "@my_module"
```

Or a specific test:

```shell
npx playwright test --project=firefox -g 'Example test'
```

To see what's happening during a running test:

```shell
npx playwright test --project=firefox -g 'Example test' --headed
```

Or run the test step by step:

```shell
npx playwright test --project=firefox -g 'Example test' --ui
```

More information: [Playwright running and debugging tests](https://playwright.dev/docs/running-tests).
