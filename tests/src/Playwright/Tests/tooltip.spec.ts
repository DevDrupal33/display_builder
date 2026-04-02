import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Tooltip', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal)
  })

  await test.step(`Check preview on Components`, async () => {
    await displayBuilder.openLibrariesTab('Components')
    const testComponent = page.getByRole('button', { name: 'Test simple', exact: true })
    await expect(testComponent).toBeVisible()
    await testComponent.hover()
    await displayBuilder.htmxReady()

    await expect(page.getByRole('tooltip')).toBeVisible()
    await expect(page.getByRole('tooltip')).toMatchAriaSnapshot({ name: 'preview-hover.aria.yml' })
  })

  await test.step(`Check preview on Blocks`, async () => {
    await displayBuilder.openLibrariesTab('Blocks')
    const testBlock = page.getByRole('button', { name: 'Powered by Drupal', exact: true })
    await testBlock.hover()
    await displayBuilder.htmxReady()

    await expect(page.getByRole('tooltip')).toBeVisible()
    await expect(page.getByRole('tooltip')).toMatchAriaSnapshot({ name: 'preview-block-hover.aria.yml' })
  })
})
