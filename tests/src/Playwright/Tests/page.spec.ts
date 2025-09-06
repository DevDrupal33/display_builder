import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_page_layout' ])
  await drupal.setPreprocessing({ css: false, javascript: false })
})

test(
  'Page Layout',
  { tag: [ '@display_builder', '@display_builder_page_layout', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const testName = utils.createRandomString()
    const name = `test_${testName}`
    const pageLayoutListRow = page.locator(`tr[data-id="${name}"]`)

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    // Create the page layout.
    await test.step(`Create page and set display`, async () => {
      await page.goto(config.pageListUrl)
      await page.getByRole('link', { name: 'Add page layout' }).click()
      await page.getByLabel('Label').fill(name)
      await page.getByLabel('Profile', { exact: true }).selectOption('test')
      // Fill some conditions.
      await page.getByRole('link', { name: 'Pages' }).click()
      await page.getByRole('textbox', { name: 'Pages' }).fill(`/test-${testName}`)
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Created new page layout')
      // Check conditions summary.
      await expect(page.getByText(`On the following pages: /test-${testName}`)).toBeVisible()
    })

    await test.step(`Check the display`, async () => {
      await pageLayoutListRow.getByRole('link', { name: 'Build display' }).click()
      await displayBuilder.shoelaceReady()

      // Enable highlight to ease drag.
      await displayBuilder.fullHighlight()

      // Test the proper blocks are available for Page context.
      const sources = {
        local_actions: '[Page] Local actions',
        local_tasks: '[Page] Local tasks',
        main_page_content: '[Page] Main content',
        page_title: '[Page] Title',
      }
      await displayBuilder.expectBlocksAvailable(sources)
    })

    await test.step(`Build the display`, async () => {
      // Basic common drag component and token.
      await displayBuilder.dragSimpleComponentsWithToken('I am a test token in a slot in a Page Layout!')

      // Result is based on the default page fixture with previous actions.
      // @see modules/display_builder_page_layout/fixtures/default_page_layout.yml
      await displayBuilder.closeDialog('both')
      await displayBuilder.saveDisplayBuilder()

      // Test only the component and token as the urls from blocks account change
      // in ci.
      await displayBuilder.expectPreviewAriaSnapshot('page.aria.yml', '.db-island-preview .test_simple ')
    })

    await test.step(`View the result page`, async () => {
      await page.goto(`test-${testName}`)
      await expect(page.locator('.page-wrapper .test_simple')).toMatchAriaSnapshot({ name: 'page-view.aria.yml' })
    })

    await test.step(`Delete the display`, async () => {
      await page.goto(config.pageListUrl)
      await pageLayoutListRow.getByRole('button', { name: 'List additional actions' }).click()
      await page.getByRole('link', { name: 'Delete Test' }).click()
      // Instance is deleted(?) not yet...
      await page.goto(config.dbList)
      await expect(page.getByRole('cell', { name: `${config.pagePrefix}${name}`, exact: true })).toBeVisible()
    })
  }
)
