# Playwright end-to-end tests (E2E)

End-to-end tests for this module use [Playwright](https://playwright.dev). Tests are under `tests/src/Playwright`.

This document provides a short Quickstart, examples for local/Docker usage and troubleshooting tips.

## Prerequisites

- Composer dev dependencies (run from repository root):

```bash
composer require drush/drush --dev
composer require drupal/core-dev:^11.3 -W --dev
```

- Node and package manager (node >= 20, npm or Yarn). From the module folder install JS deps:

```bash
cd web/modules/custom/display_builder
npm install
# or: yarn install
```

- Install Playwright browsers (recommended with system deps):

```bash
npx playwright install --with-deps
```

See the [Playwright installation docs](https://playwright.dev/docs/installation) for more details.

Notes:

- On some Linux distributions (e.g. [Fedora support](https://github.com/microsoft/playwright/issues/29559)) you may need extra system packages before installing browsers. See the [Playwright installation docs](https://playwright.dev/docs/installation#system-dependencies) for distro-specific packages. As a workaround on **Fedora**, install the following packages:

```shell
dnf install \
    libicu \
    libjpeg-turbo \
    libwebp \
    flite \
    pcre \
    libffi
```

Playwright will throw an error on install but tests will work except for Webkit.

## Quickstart

- Copy env and adjust if needed:

```bash
cd web/modules/custom/display_builder
cp .env.dist .env
```

- Run the `base` tests with firefox locally:

```bash
npx playwright test -c playwright.local.config.ts --project=firefox -g @base
# or use npm script
npm run test
```

If you need to run a single test file or grep-style selection:

```bash
npx playwright test -c playwright.local.config.ts --project=firefox page.spec.ts
npx playwright test -c playwright.local.config.ts --project=firefox -g 'Page Layout'
```

Use `--headed` or `--ui` while debugging:

```bash
npx playwright test -c playwright.local.config.ts --project=firefox -g 'Page Layout' --headed
npx playwright test -c playwright.local.config.ts --project=firefox -g 'Page Layout' --ui
```

More information on [Playwright running and debugging tests](https://playwright.dev/docs/running-tests).

Important env/settings notes:

- `DRUPAL_TEST_BASE_URL` must point to your site base, e.g. `http://localhost:8000/web`. A Webserver will launch automatically with `playwright.local.config.ts`.
- `DRUPAL_TEST_SKIP_INSTALL` allow a more specific behavior by having an installed Drupal with a running stack.
- For tests run against an installed Drupal, enable in `settings.php`:

```php
$settings['extension_discovery_scan_tests'] = TRUE;
```

- Modules that **MUST** be enabled for tests:
  - layout_builder
  - display_builder_test
  - display_builder_ui
  - display_builder_entity_view
  - display_builder_entity_view_test
  - display_builder_page_layout
  - display_builder_page_layout_test

Theme **MUST** be `display_builder_theme_test` by default, unless test is
specific for a theme.

Copy and adapt the `.env.dist` file as `.env` to set your environment.

There are different cases for running tests with a full installation or on a running Drupal instance to avoid the install step.

## Docker: remote Playwright server

When you cannot install native browsers, run a Playwright server in Docker and connect to it. See the [Playwright remote browsers docs](https://playwright.dev/docs/remote-browsers).

- Run Playwright server container:

```bash
docker pull mcr.microsoft.com/playwright:v1.58.2-noble
docker run --add-host=hostmachine:host-gateway --rm -it -p 3000:3000 \
    --workdir /home/pwuser --user pwuser \
    mcr.microsoft.com/playwright:v1.58.2-noble \
    /bin/sh -c "npx -y playwright@1.58.2 run-server --port 3000 --host 0.0.0.0"
```

- Run Webserver from Drupal root:

```bash
php -S 0.0.0.0:8000 -t web/
```

- Launch tests pointing to the remote WS endpoint:

```bash
PW_TEST_CONNECT_WS_ENDPOINT=ws://127.0.0.1:3000/ \
DRUPAL_TEST_BASE_URL='http://localhost:8000' \
    npx playwright test --project=firefox -g '@base'
```

Tips:

- Ensure ports and host mappings allow the test runner to reach the Docker container.
- If you use `--add-host=hostmachine:host-gateway` you may need to use that hostname in the container to reach the host.

## Reporting and debugging

- The config already collects traces/screenshots/videos on failures per `playwright.config.ts`.
- To open the HTML report locally after a run, open `web/modules/custom/display_builder/playwright-report/index.html`.
- To collect a trace for a failing test, re-run with `--trace on` or rely on `trace: 'on-first-retry'` from the config. View traces with the [Playwright trace viewer](https://playwright.dev/docs/trace-viewer).

## Troubleshooting (common issues)

- Playwright browser install fails: install system dependencies for your distro or use the Docker image.
- WebKit tests: WebKit is unreliable or unsupported on many Linux CI images — skip or use Chromium/Firefox in CI.
- Docker WS connection: ensure `PW_TEST_CONNECT_WS_ENDPOINT` points to the container and firewall rules allow the connection.
- Failing Drupal tests: verify required modules are enabled (layout_builder and display_builder test modules), the test theme is active (`display_builder_theme_test`), and `extension_discovery_scan_tests` is enabled when running against an installed site.
- Timeouts: `DRUPAL_TEST_SKIP_INSTALL` toggles shorter timeouts in `playwright.config.ts` — unset it for full install flows.

## Where to look in this repo

- `package.json` for test scripts
- Local Playwright config: `playwright.local.config.ts`
- Main Playwright config: `playwright.config.ts`

## Fast debug checklist

- Run a single failing spec with `--headed` and `--ui`.
- Re-run with `--trace` or open the trace in the [Playwright trace viewer](https://playwright.dev/docs/trace-viewer).
- Check `playwright-report` for screenshots/videos.
