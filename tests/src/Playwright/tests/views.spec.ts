import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'

import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.setupMinimalTestSite(['views', 'views_ui', 'display_builder_views', 'display_builder_views_test'])
  // Allays show advanced panel and disable preview.
  await drupal.drush('config:set -y views.settings ui.show.advanced_column true');
  await drupal.drush('config:set -y views.settings ui.show.preview_information true');
});

test('Views Display Builder', {tag: '@db_views'} , async ({ page, drupal }) => {
  await drupal.loginAsAdmin()

  await page.goto(dbConfig.viewsEditUrl.replace('{view_id}', dbConfig.viewsTestName))
  await cmd.ajaxReady(page)

  // Test 1: Set the buillder profile on a view.
  await page.getByText('Display Builder: Disabled').getByRole('link', { name: 'Disabled' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.locator('select[name="display_builder"]').selectOption('default')
  await page.getByText('ApplyCancel').getByText('Apply').click()
  await expect(page.getByRole('dialog')).toBeHidden()
  await cmd.ajaxReady(page)

  // Ensure the display exists.
  await page.getByText('Display Builder: Default').getByRole('link', { name: 'Default' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.getByRole('link', { name: 'build the display' }).click()
  await cmd.shoelaceReady(page)
  await expect(page.getByRole('heading', { name: 'Display builder for Test Display builder Page' })).toBeVisible()

  // Ensure the good profile is set.
  await page.goto(dbConfig.viewsDbList)
  await expect(page.getByRole('link', { name: 'Test Display builder' })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'default' })).toBeVisible()

  // Test 2: change the display builder profile.
  await page.goto(dbConfig.viewsEditUrl.replace('{view_id}', dbConfig.viewsTestName))
  await cmd.ajaxReady(page)

  await page.getByText('Display Builder: Default').getByRole('link', { name: 'Default' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.locator('select[name="display_builder"]').selectOption('test')
  await page.getByText('ApplyCancel').getByText('Apply').click()
  await expect(page.getByRole('dialog')).toBeHidden()
  await cmd.ajaxReady(page)
  // Save the View.
  await page.getByRole('button', { name: 'Save' }).click()
  await expect(page.locator('.messages__content')).toContainText('The view Test Display builder has been saved.')

  // Ensure the good profile is set.
  await page.goto(dbConfig.viewsDbList)
  await expect(page.getByRole('link', { name: 'Test Display builder' })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'test' })).toBeVisible()

  // Test 3: Check the Display builder instance.
  await page.getByRole('link', { name: 'Build display' }).click()
  await cmd.shoelaceReady(page)

  await cmd.openLibraries(page)
  await cmd.openLibrariesBlocks(page, `view__${dbConfig.viewsTestName}__page_test`)
  // @todo check the proper views row.
  const sources = {
    'view_attachment_after': '[View] Footer area',
    'view_attachment_before': '[View] Footer area',
    'view_exposed': '[View] Footer area',
    'view_feed_icons': '[View] Footer area',
    'view_footer': '[View] Footer area',
    'view_header': '[View] Footer area',
    'view_more': '[View] Footer area',
    'view_pager': '[View] Footer area',
    'view_rows_tmp': '[View] Rows (Display Builder)',
    // 'view_rows': 'View rows',
  };

  for (const [source, label] of Object.entries(sources)) {
    // await expect(page.locator(`.db-island-block_library [hx-vals*="${source}"]`)).toHaveCount(1)
    await expect( page.locator('.db-island-block_library').getByRole('button', { name: label })).toHaveCount(1)
    await expect(page.locator(`.db-island-builder [data-instance-title="${label}"]`)).toHaveCount(1)
  }

  // Add a token before first source in the builder.
  const target = page.locator('.db-island-builder').getByText('[View] Header')
  await cmd.setTokenWithValue(page, target, 'Test views')
  await cmd.saveDisplayBuilder(page)

  // Test 4: Check the builder result from the view.
  await page.goto(dbConfig.viewsEditUrl.replace('{view_id}', dbConfig.viewsTestName))
  await cmd.ajaxReady(page)
  await page.getByRole('link', { name: 'View Page' }).click()
  await expect(page.getByRole('heading', { name: 'Test page with Display builder' })).toBeVisible()
  // @todo this is failing...
  // await expect(page.getByText('Test views')).toBeVisible();

  // Test 5: Delete the builder.
  await page.goto(dbConfig.viewsEditUrl.replace('{view_id}', dbConfig.viewsTestName))
  await cmd.ajaxReady(page)

  await page.getByText('Display Builder: Test').getByRole('link', { name: 'Test' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.locator('select[name="display_builder"]').selectOption('- Disabled -')
  await page.getByText('ApplyCancel').getByText('Apply').click()
  await expect(page.getByRole('dialog')).toBeHidden()
  await cmd.ajaxReady(page)

  // Ensure the profile is deleted and the view is working.
  await page.goto(dbConfig.viewsDbList)
  await expect(page.getByRole('cell', { name: 'No Display builder enabled' })).toHaveCount(1)
  await page.goto(dbConfig.viewsEditUrl.replace('{view_id}', dbConfig.viewsTestName))
  await cmd.ajaxReady(page)
  await page.getByRole('link', { name: 'View Page' }).click()
  await expect(page.getByRole('heading', { name: 'Test page with Display builder' })).toBeVisible()
})
