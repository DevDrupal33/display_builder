import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'

import * as utils from '../utilities/utils'

import config from '../playwright.config.loader'

// Local tests as the Display Builder must be created manually.
// This is internal and used for creating and fixing tests, mostly position.
test('Drag and drop', { tag: [ '@display_builder_local' ] }, async ({ page, drupal, displayBuilder }) => {
  const dbName = `test_dnd`

  await drupal.loginAsAdmin()
  await page.goto(config.dbViewUrl.replace('{db_id}', dbName))

  if ((await page.getByText(`Missing ${dbName} config.`).count()) === 1) {
    await displayBuilder.createDisplayBuilderFromUi(dbName)
  }

  await displayBuilder.shoelaceReady()
  await displayBuilder.htmxReady()

  // Enable highlight to ease drag.
  await displayBuilder.keyboardShortcut('Shift+H')

  await displayBuilder.dragElementFromLibraryById(
    'Blocks',
    'token',
    page.locator(`.db-island-builder > slot.db-dropzone`)
  )
  await displayBuilder.dragElementFromLibraryById(
    'Blocks',
    'token',
    page.locator(`.db-island-builder > slot.db-dropzone`)
  )
})
