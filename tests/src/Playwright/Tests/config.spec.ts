import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Config form', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal)
  })

  await test.step('Drag component', async () => {
    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_complex',
      page.locator(`.db-island-builder > div.db-dropzone`).first(),
      { x: 40, y: 15 },
    )
  })

  await test.step(`Apply config on component`, async () => {
    await page.getByRole('heading', { name: 'label: none, open: false, duration: 0' }).click()

    await expect(page.getByRole('tab', { name: 'Config', exact: true })).toBeVisible()

    await page.getByRole('tab', { name: 'Config', exact: true }).click()
    await displayBuilder.shoelaceReady()

    await page.getByRole('button', { name: 'Attributes', exact: true }).click()
    await page.getByRole('textbox', { name: 'Attributes', exact: true }).fill('data-foo="bar"')
    await page.getByRole('button', { name: 'Test attributes', exact: true }).click()
    await page.getByRole('textbox', { name: 'Test attributes', exact: true }).fill('data-bar="foo"')

    await page.getByRole('button', { name: 'Label' }).click()
    await page.getByRole('textbox', { name: 'Label' }).fill('Test Label')
    await page.getByRole('button', { name: 'Open' }).click()
    await page.getByRole('checkbox', { name: 'Open' }).uncheck()
    await page.getByRole('button', { name: 'Duration' }).click()
    await page.getByRole('spinbutton', { name: 'Duration' }).fill('100')

    // Click somewhere for htmx submit
    await page.getByRole('tab', { name: 'Builder' }).click()
    await displayBuilder.shoelaceReady()
    await displayBuilder.htmxReady()
  })

  await test.step(`Check result`, async () => {
    await expect(page.locator('.db-island-builder [data-test="test-parent"]')).toHaveAttribute('data-foo', 'bar')
    await expect(page.locator('.db-island-builder [data-test="test-child"]')).toHaveAttribute('data-bar', 'foo')

    await displayBuilder.expectPreviewAriaSnapshot('config.aria.yml')
  })
})
