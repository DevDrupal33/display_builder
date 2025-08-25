import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'

import dbConfig from '../playwright.db.config'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_page_layout', 'display_builder_page_layout_test' ])
})

test(
  'Page Layout',
  { tag: [ '@display_builder', '@display_builder_page_layout', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    await drupal.loginAsAdmin()

    await page.goto(dbConfig.pageListUrl)
    await page.getByRole('link', { name: 'Build display' }).click()

    await displayBuilder.shoelaceReady()
    await displayBuilder.htmxReady()
    // Enable highlight to ease drag.
    await displayBuilder.keyboardShortcut('Shift+H')

    await displayBuilder.toggleSidebarView()
    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )

    await displayBuilder.deleteDisplayBuilderFromUi('page_layout__test')
  }
)
