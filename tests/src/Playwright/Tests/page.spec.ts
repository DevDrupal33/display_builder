import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'

import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.setupMinimalTestSite(['display_builder_page_layout', 'display_builder_page_layout_test'])
});

test('Page Layout', {tag: ['@display_builder', '@display_builder_page_layout', '@display_builder_min']} , async ({ page, drupal }) => {
  await drupal.loginAsAdmin()

  await page.goto(dbConfig.pageListUrl)
  await page.getByRole('link', { name: 'Build display' }).click()

  await cmd.htmxReady(page)

  await cmd.toggleSidebarView(page)
  await cmd.dragElementFromLibraryById(page, 'Components', 'test_simple', page.locator(`.db-island-builder > slot.db-dropzone`))
})
