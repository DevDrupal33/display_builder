import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// DesignTokensPanel (a Contextual island, ui_skins) overrides CSS variables on
// the active node: the form lists the css_variable plugins, and on submit
// alterElement() writes them as an inline `style="--var: value;"` on the node's
// element (filterValues drops any left at their default). The test theme
// defines test_token_1 (default "foo") and test_token_2 (default "bar").
// @see \Drupal\display_builder\Plugin\display_builder\Island\DesignTokensPanel
// @see tests/themes/display_builder_theme_test/display_builder_theme_test.ui_skins.css_variables.yml

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'ui_skins' ])
})

test('Apply a design token to a node', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const component = page.locator('.db-island-builder [data-test="test_simple"]').first()

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileExtraId)
  })

  await test.step(`Drop a component`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(component).toHaveCount(1)
  })

  await test.step(`Override a design token`, async () => {
    await component.click({ position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await page.getByTestId('tab_contextual_tokens').click()
    await displayBuilder.shoelaceReady()

    await page.getByRole('button', { name: 'Testing' }).click()
    const field = page.getByRole('textbox', { name: 'Test token 1', exact: true })
    await field.fill('red')
    await field.blur()
    await displayBuilder.htmxReady()
  })

  await test.step(`The token renders as an inline CSS variable`, async () => {
    await expect(
      page.locator('.db-island-builder [style*="--test-token-1: red"]'),
    ).toHaveCount(1)
  })

  await test.step(`The token survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(
      page.locator('.db-island-builder [style*="--test-token-1: red"]'),
    ).toHaveCount(1)
  })
})
