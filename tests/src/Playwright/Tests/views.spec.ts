import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['views', 'views_ui', 'display_builder_views', 'display_builder_views_test'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
  // Allays show advanced panel and disable preview.
  await drupal.drush('config:set -y views.settings ui.show.advanced_column true')
  await drupal.drush('config:set -y views.settings ui.show.preview_information true')
})

test(
  'Views',
  { tag: ['@display_builder', '@display_builder_views', '@display_builder_min'] },
  async ({ page, drupal, displayBuilder }) => {
    const testName = utils.createRandomString()
    const name = `test_${testName}`

    // We have a specific attribute in:
    // @see modules/display_builder_views/src/Controller/ViewsManagementController::buildRow()
    const listProfileId = page.locator(`[data-profile-id="profile_${name}"]`)

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    await test.step(`Create view and fill areas`, async () => {
      await page.goto(config.viewsAddUrl)
      await page.getByLabel('View name').fill(`Test ${testName}`)
      await page.getByLabel('Create a page').check()
      await page.getByText('Save and edit').click()
      await drupal.expectMessage(`The view Test ${testName} has been saved.`)
      await drupal.ajaxReady()

      const applyDialog = page.getByText('ApplyCancelRemove').getByRole('button', { name: 'Apply' })

      // Set areas to check the result.
      await page.getByRole('link', { name: 'Add header' }).click()
      await drupal.ajaxReady()
      await page.getByRole('checkbox', { name: 'Update Text area' }).check()
      await page.getByRole('button', { name: 'Add and configure header' }).click()
      await page.getByRole('textbox', { name: 'Content' }).fill('This is a header views area')
      await applyDialog.click()
      await drupal.ajaxReady()

      await page.getByRole('link', { name: 'Add footer' }).click()
      await drupal.ajaxReady()
      await page.getByRole('row', { name: 'Update Text area Text area' }).locator('div').click()
      await page.getByRole('checkbox', { name: 'Update Text area' }).check()
      await page.getByRole('button', { name: 'Add and configure footer' }).click()
      await page.getByRole('textbox', { name: 'Content' }).fill('This is a footer views test area')
      await applyDialog.click()
      await drupal.ajaxReady()

      await page.getByRole('link', { name: 'Add no results behavior' }).click()
      await drupal.ajaxReady()
      await page.getByRole('checkbox', { name: 'Update Text area' }).check()
      await page.getByRole('button', { name: 'Add and configure no results' }).click()
      await page.getByRole('textbox', { name: 'Content' }).fill('This is the no results views area')
      await applyDialog.click()
      await drupal.ajaxReady()

      await page.getByRole('link', { name: 'Content: Published (= Yes)' }).click()
      await drupal.ajaxReady()
      await page.getByRole('checkbox', { name: 'Expose this filter to visitors' }).check()
      await applyDialog.click()
      await drupal.ajaxReady()

      await page.getByRole('link', { name: 'Content: Authored on (desc)' }).click()
      await drupal.ajaxReady()
      await page.getByRole('checkbox', { name: 'Expose this sort to visitors' }).check()
      await applyDialog.click()
      await drupal.ajaxReady()
    })

    await test.step(`Set view display`, async () => {
      // Set the builder profile on a view.
      await page.locator('.views-display-setting').getByText('Disabled').click()
      await expect(page.getByLabel('Profile', { exact: true })).toBeVisible()
      await page.getByLabel('Profile', { exact: true }).selectOption('default')
      await page.getByText('ApplyCancel').getByText('Apply').click()
      await drupal.ajaxReady()

      // Save the View.
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage(`The view Test ${testName} has been saved.`)
    })

    await test.step(`Set and switch profile`, async () => {
      await drupal.ajaxReady()
      await page.getByText('Display Builder: Default').getByRole('link', { name: 'Default' }).click()
      await expect(page.getByRole('link', { name: 'build the display' })).toBeVisible()
      await page.getByRole('link', { name: 'build the display' }).click()
      await displayBuilder.shoelaceReady()
      await expect(page.getByRole('heading', { name: `Display builder for Test ${testName} Page` })).toBeVisible()

      // Ensure the good profile is set.
      await page.goto(config.viewsDbList)
      await expect(page.getByRole('link', { name: `Test ${testName}` })).toBeVisible()
      await expect(listProfileId).toHaveText('default')

      // Change the display builder profile.
      await page.goto(config.viewsEditUrl.replace('{view_id}', name))
      await drupal.ajaxReady()

      await page.getByText('Display Builder: Default').getByRole('link', { name: 'Default' }).click()
      await expect(page.getByLabel('Profile', { exact: true })).toBeVisible()
      await page.getByLabel('Profile', { exact: true }).selectOption('test')
      await page.getByText('ApplyCancel').getByText('Apply').click()
      await drupal.ajaxReady()

      // Save the View.
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage(`The view Test ${testName} has been saved.`)

      // Ensure the good profile is set.
      await page.goto(config.viewsDbList)
      await expect(page.getByRole('link', { name: `Test ${testName}` })).toBeVisible()
      await expect(listProfileId).toHaveText('test')
    })

    await test.step(`Check the display`, async () => {
      await page.locator(`[data-link-builder="${config.viewsPrefix}${name}__page_1"]`).click()
      await displayBuilder.shoelaceReady()

      await displayBuilder.fullHighlight()

      // Test the proper blocks are available for Views context.
      // @todo check the proper views row.
      const sources = {
        view_header: '[View] Header',
        view_exposed: '[View] Exposed',
        view_attachment_before: '[View] Attachment_before',
        view_rows_tmp: '[View] Rows',
        // 'view_rows': 'View rows',
        view_pager: '[View] Pager',
        view_attachment_after: '[View] Attachment_after',
        view_more: '[View] More',
        view_footer: '[View] Footer',
        view_feed_icons: '[View] Feed_icons',
      }
      await displayBuilder.expectBlocksAvailable(sources)
    })

    await test.step(`Build the display`, async () => {
      await displayBuilder.dragSimpleComponentsWithToken('I am a test token in a slot in a View!')

      // Result is based on the default page fixture with previous actions.
      // @see modules/display_builder_views/fixtures/default_view.yml
      await displayBuilder.closeDialog('both')
      await displayBuilder.publishDisplayBuilder()
      await displayBuilder.expectPreviewAriaSnapshot('view.aria.yml')
    })

    await test.step(`Check the view result page`, async () => {
      await page.goto(config.viewsEditUrl.replace('{view_id}', name))
      await drupal.ajaxReady()

      // @todo to test full rendered view we must fill every source.
      await page.getByRole('link', { name: 'View Page' }).click()
      await expect(page.getByRole('heading', { name: `Test ${testName}` })).toBeVisible()
      await expect(page.locator('.views-element-container')).toMatchAriaSnapshot({ name: 'view-view.aria.yml' })
    })

    await test.step(`Delete the display`, async () => {
      await page.goto(config.viewsEditUrl.replace('{view_id}', name))
      await drupal.ajaxReady()

      await page.getByText('Display Builder: Test').getByRole('link', { name: 'Test' }).click()
      await expect(page.getByLabel('Profile', { exact: true })).toBeVisible()
      await page.getByLabel('Profile', { exact: true }).selectOption('- Disabled -')
      await page.getByText('ApplyCancel').getByText('Apply').click()
      await drupal.ajaxReady()

      await expect(page.getByRole('button', { name: 'Save' })).toBeVisible()
      await page.getByRole('button', { name: 'Save' }).click()

      // Ensure the instance is deleted and the view is working.
      await page.goto(config.viewsDbList)
      await expect(page.getByRole('link', { name: `Test ${testName}` })).not.toBeVisible()
      await page.goto(config.viewsEditUrl.replace('{view_id}', name))
      await drupal.ajaxReady()

      await page.getByRole('link', { name: 'View Page' }).click()
      await expect(page.getByRole('heading', { name: `Test ${testName}` })).toBeVisible()
      await expect(page.getByText('I am a test token in a views')).not.toBeVisible()
    })
  }
)
