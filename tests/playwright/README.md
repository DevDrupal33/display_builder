# Testing Display Builder

## Local DDev

```shell
ddev add-on get Lullabot/ddev-playwright
mkdir -p test/
cp -r web/modules/custom/display_builder/tests/playwright test/
ddev exec -d /var/www/html/test/playwright npm install
ddev install-playwright
```

### Usage

```shell
# To run playwright's test command.
ddev playwright test
# To run with the UI.
ddev playwright test --headed
# To run with the UI and step control.
ddev playwright test --ui
# To generate playwright code by browsing.
ddev playwright codegen

```

## Local manual

Not recommended, support only Debian and Ubuntu.

```shell
npm install
npx playwright install-deps
npx playwright install
```
