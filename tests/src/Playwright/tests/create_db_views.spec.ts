import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test('Views Display Builder', async ({ page, drupal }) => {

  await drupal.setupMinimalTestSite(['views', 'views_ui', 'display_builder_views', 'display_builder_views_test'])
  await drupal.loginAsAdmin()

  // Test 1: Set the buillder profile on a view.
  await page.goto(dbConfig.viewsEditUrl.replace('{view_id}', dbConfig.viewsTestName))
  await page.getByRole('button', { name: 'Advanced' }).click()
  await page.getByText('Display Builder: Disabled').getByRole('link', { name: 'Disabled' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()

  await page.locator('select[name="display_builder"]').selectOption('test')
  await page.getByText('ApplyCancel').getByText('Apply').click()
  await expect(page.getByRole('dialog')).toBeHidden()
  await page.getByRole('button', { name: 'Advanced' }).click()
  await expect(page.getByText('Display Builder: Test')).toBeVisible()
  await page.getByRole('link', { name: 'Test', exact: true }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.getByText('ApplyCancel').getByText('Cancel').click()
  // Save the view.
  await page.locator('#edit-actions').getByRole('button', { name: 'Save' }).click()

  // Test 2: Check the Display builder instance.
  await page.goto(dbConfig.viewsDbList)
  await expect(page.getByRole('link', { name: 'Test Display builder' })).toBeVisible()
  await page.getByRole('link', { name: 'Build display' }).click()
  await cmd.shoelaceReady(page)

  await cmd.openLibrariesBlocks(page)
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

})
