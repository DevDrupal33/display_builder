import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'
import { getRootDir } from '../utilities/DrupalFilesystem';

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

// Local tests as the Display Builder must be created manually.
test('Drag and drop', {tag: ['@display_builder_local']}, async ({ page, drupal }) => {
  const dbName = `test_dnd`
  await drupal.loginAsAdmin()
  await page.goto(dbConfig.dbViewUrl.replace('{db_id}', dbName))

  await cmd.shoelaceReady(page)
  await cmd.htmxReady(page)

  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
})
