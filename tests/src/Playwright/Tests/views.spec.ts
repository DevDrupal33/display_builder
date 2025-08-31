import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'views', 'views_ui', 'display_builder_views', 'display_builder_views_test' ])
  // Allays show advanced panel and disable preview.
  await drupal.drush('config:set -y views.settings ui.show.advanced_column true')
  await drupal.drush('config:set -y views.settings ui.show.preview_information true')

  await drupal.setPreprocessing({ css: false, javascript: false })
})

test(
  'Views',
  { tag: [ '@display_builder', '@display_builder_views', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const testName = utils.createRandomString(6).toLowerCase()
    const name = `test_${testName}`

    // We have a specific attribute in:
    // @see modules/display_builder_views/src/Controller/ViewsManagementController::buildRow()
    const listProfileId = page.locator(`[data-profile-id="profile_${name}"]`)

    await drupal.loginAsAdmin()

    // Create a view for our display builder tests.
    await page.goto(config.viewsAddUrl)
    await page.getByLabel('View name').fill(`Test ${testName}`)
    await page.getByLabel('Create a page').check()
    await page.getByText('Save and edit').click()
    await drupal.expectMessage(`The view Test ${testName} has been saved.`)

    // Test 1: Set the builder profile on a view.
    await page.locator('.views-display-setting').getByText('Disabled').click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByLabel('Profile', { exact: true }).selectOption('default')
    await page.getByText('ApplyCancel').getByText('Apply').click()
    await expect(page.getByRole('dialog')).toBeHidden()
    await drupal.ajaxReady()
    // Save the View.
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage(`The view Test ${testName} has been saved.`)

    // Ensure the display exists.
    await page.getByText('Display Builder: Default').getByRole('link', { name: 'Default' }).click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByRole('link', { name: 'build the display' }).click()
    await displayBuilder.shoelaceReady()
    await expect(page.getByRole('heading', { name: `Display builder for Test ${testName} Page` })).toBeVisible()

    // Ensure the good profile is set.
    await page.goto(config.viewsDbList)
    await expect(page.getByRole('link', { name: `Test ${testName}` })).toBeVisible()
    await expect(listProfileId).toHaveText('default')

    // Test 2: change the display builder profile.
    await page.goto(config.viewsEditUrl.replace('{view_id}', name))
    await drupal.ajaxReady()

    await page.getByText('Display Builder: Default').getByRole('link', { name: 'Default' }).click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByLabel('Profile', { exact: true }).selectOption('test')
    await page.getByText('ApplyCancel').getByText('Apply').click()
    await expect(page.getByRole('dialog')).toBeHidden()
    await drupal.ajaxReady()
    // Save the View.
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage(`The view Test ${testName} has been saved.`)

    // Ensure the good profile is set.
    await page.goto(config.viewsDbList)
    await expect(page.getByRole('link', { name: `Test ${testName}` })).toBeVisible()
    await expect(listProfileId).toHaveText('test')

    // Test 3: Check the Display builder instance.
    await page.locator(`[data-link-builder="view__${name}__page_1"]`).click()
    await displayBuilder.shoelaceReady()

    // Enable highlight to ease drag.
    await displayBuilder.keyboardShortcut('Shift+H')

    // Test the proper blocks are available for Views context.
    // @todo check the proper views row.
    const sources = {
      view_attachment_after: '[View] Attachment after',
      view_attachment_before: '[View] Attachment before',
      view_exposed: '[View] Exposed form',
      view_feed_icons: '[View] Feed icons',
      view_footer: '[View] Footer',
      view_header: '[View] Header',
      view_more: '[View] More',
      view_pager: '[View] Pager',
      view_rows_tmp: '[View] Rows (Display Builder)',
      // 'view_rows': 'View rows',
    }
    await displayBuilder.expectBlocksAvailable(sources)

    // Basic common drag component and token.
    await displayBuilder.dragSimpleComponentsWithToken('I am a test token in a slot in a View!')

    // Result is based on the default page fixture with previous actions.
    // @see modules/display_builder_views/fixtures/default_view.yml
    await displayBuilder.closeDialog('both')
    await displayBuilder.saveDisplayBuilder()
    await displayBuilder.expectPreviewAriaSnapshot('view.aria.yml')

    // Test 4: Check the builder result from the view.
    await page.goto(config.viewsEditUrl.replace('{view_id}', name))
    await drupal.ajaxReady()

    // @todo to test full rendered view we must fill every source.
    await page.getByRole('link', { name: 'View Page' }).click()
    await expect(page.getByRole('heading', { name: `Test ${testName}` })).toBeVisible()
    await expect(page.locator('.views-element-container')).toMatchAriaSnapshot({ name: 'view-view.aria.yml' })

    // Test 5: Delete the builder.
    await page.goto(config.viewsEditUrl.replace('{view_id}', name))
    await drupal.ajaxReady()

    await page.getByText('Display Builder: Test').getByRole('link', { name: 'Test' }).click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByLabel('Profile', { exact: true }).selectOption('- Disabled -')
    await page.getByText('ApplyCancel').getByText('Apply').click()
    await drupal.ajaxReady()
    await expect(page.getByRole('dialog')).toBeHidden()
    await page.getByRole('button', { name: 'Save' }).click()

    // Ensure the profile is deleted and the view is working.
    await page.goto(config.viewsDbList)
    await expect(page.getByRole('link', { name: `Test ${testName}` })).not.toBeVisible()
    await page.goto(config.viewsEditUrl.replace('{view_id}', name))
    await drupal.ajaxReady()

    await page.getByRole('link', { name: 'View Page' }).click()
    await expect(page.getByRole('heading', { name: `Test ${testName}` })).toBeVisible()
    await expect(page.getByText('I am a test token in a views')).not.toBeVisible()
  }
)
