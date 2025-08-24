import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'
import { getRootDir } from '../utilities/DrupalFilesystem';

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

let dbName: string
// Click position required to avoid icon to intercept the click.
const position = { position: { x: 5, y: 5 } }

test.afterEach('Clean', async ({ drupal, page }) => {
  await cmd.deleteDisplayBuilderFromUi(page, dbName)
})

test('Toolbar buttons', { tag: ['@display_builder', '@display_builder_min'] }, async ({ page, drupal }) => {
  dbName = `test_${utils.createRandomString(6)}`

  await drupal.loginAsAdmin()
  await cmd.createDisplayBuilderFromUi(page, dbName)
  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
  await cmd.closeDialog(page)

  // Test the undo/redo/clear buttons
  const builderToken = page.locator(`.db-island-builder [data-instance-title="Token"]`)
  await expect(builderToken).toHaveCount(2)
  // Position required to avoid icon to intercept the click.
  const undo = page.getByRole('button', { name: '2' })
  await undo.click(position)
  await expect(builderToken).toHaveCount(1)

  const redo = page.locator('#button-1--2').getByRole('button', { name: '1' })
  await redo.click(position)
  await expect(builderToken).toHaveCount(2)

  const clear = page.getByRole('button', { name: 'Clear' })
  await clear.click(position)
  await expect(builderToken).toHaveCount(2)
  await expect(page.getByRole('button', { name: 'Undo' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Redo' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Clear' })).not.toBeVisible()

  // @todo test is stuck on keyboard.
  // await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
  // await cmd.closeDialog(page)
  // await expect(builderToken).toHaveCount(3)
  // await page.keyboard.press('u');
  // await page.waitForTimeout(dbConfig.keyboardTimeout);
  // await cmd.htmxReady(page);
  // await expect(builderToken).toHaveCount(2)
  // await page.keyboard.press('r');
  // await page.waitForTimeout(dbConfig.keyboardTimeout);
  // await cmd.htmxReady(page);
  // await expect(builderToken).toHaveCount(3)
  // await page.keyboard.press('Shift+C');
  // await page.waitForTimeout(dbConfig.keyboardTimeout);
  // await cmd.htmxReady(page);
  // await expect(builderToken).toHaveCount(3)
  // await expect(page.getByRole('button', { name: 'Undo' })).toBeVisible()
  // await expect(page.getByRole('button', { name: 'Redo' })).toBeVisible()
  // await expect(page.getByRole('button', { name: 'Clear' })).not.toBeVisible()

  const keyboard = page.getByRole('button', { name: 'Keyboard help' })
  const keyboardHelp = page.getByText('Keyboard help')

  await keyboard.click(position)
  await expect(keyboardHelp).toBeVisible()
  await keyboard.click(position)
  await expect(keyboardHelp).not.toBeVisible()
  await page.keyboard.press('h');
  await page.waitForTimeout(dbConfig.keyboardTimeout);
  await expect(keyboardHelp).toBeVisible()
  await page.keyboard.press('h');
  await page.waitForTimeout(dbConfig.keyboardTimeout);
  await expect(keyboardHelp).not.toBeVisible()

  const fullscreen = page.getByRole('button', { name: 'Display the builder as fullscreen.' })
  const fullscreenIsOn = page.locator('.display-builder--fullscreen')

  await fullscreen.click(position)
  await expect(fullscreenIsOn).toBeVisible()
  await fullscreen.click(position)
  await expect(fullscreenIsOn).not.toBeVisible()
  await page.keyboard.press('Shift+M');
  await page.waitForTimeout(dbConfig.keyboardTimeout);
  await expect(fullscreenIsOn).toBeVisible()
  await page.keyboard.press('Shift+M');
  await page.waitForTimeout(dbConfig.keyboardTimeout);
  await expect(fullscreenIsOn).not.toBeVisible()

  const highlight = page.getByRole('button', { name: 'Highlight components, blocks and slots.' })
  const highlightIsOn = page.locator('.display-builder--highlight')

  await highlight.click(position)
  await expect(highlightIsOn).toBeVisible()
  await highlight.click(position)
  await expect(highlightIsOn).not.toBeVisible()
  await page.keyboard.press('Shift+H');
  await page.waitForTimeout(dbConfig.keyboardTimeout);
  await expect(highlightIsOn).toBeVisible()
  await page.keyboard.press('Shift+H');
  await page.waitForTimeout(dbConfig.keyboardTimeout);
  await expect(highlightIsOn).not.toBeVisible()

  const switchViewport = page.locator(`#island-${dbName}-viewport`)
  const switchViewportList = page.locator('#listbox')

  await switchViewport.click()

  await expect(
    switchViewportList,
  ).toMatchAriaSnapshot(`
  - listbox [expanded]:
    - option "Fluid" [selected]
    - separator
    - text: db_theme_test
    - option "Extra small"
    - option "Small and smaller"
    - option "Medium and smaller"
    - option "Large and smaller"
    - option "Extra large and smaller"
  `);

  await page.getByRole('option', { name: 'Extra small' }).locator('slot').nth(1).click()
  await expect(switchViewportList).not.toBeVisible()
  await expect(page.locator('.display-builder__main')).toHaveAttribute('style', 'max-width: 575px;')

  await switchViewport.click()
  await page.getByRole('option', { name: 'Fluid' }).locator('slot').nth(1).click()
  await expect(switchViewportList).not.toBeVisible()
  await expect(page.locator('.display-builder__main')).toHaveAttribute('style', 'max-width: 100%;')
})
