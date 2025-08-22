import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'
import { getRootDir } from '../utilities/DrupalFilesystem';

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test('Toolbar buttons', {tag: ['@display_builder', '@display_builder_min']}, async ({ page, drupal }) => {
  const dbName = `test_${utils.createRandomString(6)}`

  await drupal.loginAsAdmin()

  await cmd.createDisplayBuilderFromUi(page, dbName)

  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))

  await cmd.setElementValue(page,
    page.locator(`.db-island-builder [data-instance-title="Token"]`),
    'I am a token',
    [
      {
        action: 'fill',
        locator: page.locator('#edit-value'),
      }
    ]
  )

  await cmd.closeDialog(page)
  await cmd.closeDialog(page, 'second')

  // Test the keyboard button
  const keyboard = page.getByRole('button', { name: 'Keyboard help' })
  const keyboardHelp = page.getByText('Keyboard help')
  // Position required to avoid icon to intercept the click.
  await keyboard.click({position: { x: 5, y: 5 }})
  await expect(keyboardHelp).toBeVisible()
  await keyboard.click({position: { x: 5, y: 5 }})
  await expect(keyboardHelp).not.toBeVisible()
  await page.keyboard.press('h');
  await expect(keyboardHelp).toBeVisible()
  await page.keyboard.press('h');
  await expect(keyboardHelp).not.toBeVisible()

  // Test the fullscreen button
  const fullscreen = page.getByRole('button', { name: 'Display the builder as fullscreen.' })
  const fullscreenIsOn = page.locator('.display-builder--fullscreen')
  // Position required to avoid icon to intercept the click.
  await fullscreen.click({position: { x: 5, y: 5 }})
  await expect(fullscreenIsOn).toBeVisible()
  await fullscreen.click({position: { x: 5, y: 5 }})
  await expect(fullscreenIsOn).not.toBeVisible()
  await page.keyboard.press('Shift+M');
  await expect(fullscreenIsOn).toBeVisible()
  await page.keyboard.press('Shift+M');
  await expect(fullscreenIsOn).not.toBeVisible()

  // Test the highlight button
  const highlight = page.getByRole('button', { name: 'Highlight components, blocks and slots.' })
  const highlightIsOn = page.locator('.display-builder--highlight')
  await highlight.click({position: { x: 5, y: 5 }})
  await expect(highlightIsOn).toBeVisible()
  await highlight.click({position: { x: 5, y: 5 }})
  await expect(highlightIsOn).not.toBeVisible()
  await page.keyboard.press('Shift+H');
  await expect(highlightIsOn).toBeVisible()
  await page.keyboard.press('Shift+H');
  await expect(highlightIsOn).not.toBeVisible()

  // Test the switch viewport button
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
